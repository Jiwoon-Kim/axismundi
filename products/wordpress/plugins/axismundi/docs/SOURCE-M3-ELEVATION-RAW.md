# Material 3 Elevation Raw Source

## Purpose

Preserve the supplied upstream reading material before Axismundi chooses a Social
frontend implementation. Do not rewrite this file to describe local CSS; local
decisions belong in `DECISION-FRONTEND-ELEVATION-OWNERSHIP.md` and the distilled
implementation index belongs in `REFERENCE-M3-ELEVATION.md`.

## Source URLs

- https://m3.material.io/styles/elevation/overview
- https://m3.material.io/styles/elevation/applying-elevation
- https://github.com/material-components/material-web/blob/919fe12badcfee4dcd72c390c0869dd8f996b51c/docs/components/elevation.md
- https://github.com/material-components/material-web/tree/main/elevation
- https://github.com/material-components/material-web/blob/main/tokens/_md-comp-elevation.scss
- https://github.com/material-components/material-web/blob/919fe12badcfee4dcd72c390c0869dd8f996b51c/docs/theming/README.md
- https://material-web.dev/about/size/

## Preserved source notes

- Elevation applies to surfaces and components. It codifies relative z-axis distance
  so components consistently relate to one another.
- An elevation token has no inherent shadow or colour. A platform chooses the visual
  rendering for a level.
- Tonal surface differences, shadows, and scrims can depict elevation. Surface colour
  roles are not intrinsically tied to elevation.
- Use a small number of levels. Do not change a Material component's default resting
  elevation without a component-specific reason.
- Resting levels are `0` through `3`; levels `4` and `5` are reserved for transient
  interaction such as hover and dragging.

| Level | Relative distance |
| --- | ---: |
| `0` | `0dp` |
| `1` | `1dp` |
| `2` | `3dp` |
| `3` | `6dp` |
| `4` | `8dp` |
| `5` | `12dp` |

The source describes elevation as a relationship between surfaces. It can establish
scrolling order, communicate a floating surface's separation from surrounding content,
and direct attention to a temporary foreground surface such as a dialog.

### Depiction guidance

- A visible elevated surface needs a perceivable edge, overlap or motion relationship,
  and distance from surrounding surfaces.
- Material defaults to tonal separation. Shadows or a scrim may be used where tonal
  separation alone is insufficient, especially against busy backgrounds.
- Shadows should be used sparingly. A smaller, sharper shadow indicates closer
  proximity; a larger, softer shadow indicates greater distance.
- Scrims are placed beneath modal or expanded navigation surfaces. The source calls for
  the scrim colour role at `32%` opacity.

### Component resting-level index

| Resting level | Material examples |
| --- | --- |
| `3` | Date picker, modal dialog, extended FAB, FAB, search, time picker |
| `2` | Scrolled app bar, menu, navigation bar, rich tooltip, toolbar |
| `1` | Banner, modal bottom sheet, elevated button/card/chip, modal navigation drawer, modal side sheet |
| `0` | Unscrolled app bar, filled/tonal/outlined buttons, button groups, filled/outlined cards, icon buttons, list, navigation rail, tabs |

Levels `4` and `5` are intentionally not assigned as component resting levels in the
source table. A hover commonly raises a component one level, for example a FAB from
level `3` to level `4`.

## Material Web implementation pointers

- `@material/web/elevation/elevation.js`
- Material Web's elevation component documentation and component token source listed
  above

