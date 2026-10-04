# Source: M3 Navigation bar / Navigation rail (raw)

/ 캡처 2026-10-03. `m3.material.io`는 JS로 본문을 그리기 때문에 `WebFetch`로는 제목만
/ 돌아온다. 아래는 브라우저로 렌더한 뒤 읽은 overview 본문이다. 다른 `SOURCE-M3-*-RAW.md`와
/ 같은 성격의 1차 자료이며, 해석은 `PLAN-FRONTEND-NAVIGATION-COMPONENTS.md`에 있다.
/
/ Specs / Guidelines / Accessibility 탭은 아직 캡처하지 않았다. 실제 구현에 들어갈 때
/ measurements와 token 목록을 같은 방식으로 가져와야 한다.

## Navigation bar — Overview

```text
Use navigation bars in compact or medium window sizes
Can contain 3-5 destinations of equal importance
Destinations don't change. They should be consistent across app screens.

Navigation bar for compact and medium window sizes
```

### M3 Expressive update (May 2025)

```text
A new flexible navigation bar was introduced to replace the baseline navigation
bar. It's shorter and supports horizontal navigation items in medium windows.

Variants and naming:
  Baseline navigation bar is no longer recommended
  Added flexible navigation bar
    Shorter height
    Can be used in medium window sizes with horizontal navigation items

Color:
  Active label changed from on-surface-variant to secondary

The flexible navigation bar is shorter and can be used in medium windows with
horizontal nav items
```

### Differences from M2

```text
Color:     New color mappings and compatibility with dynamic color
Elevation: No shadow
Layout:    Container height is taller
States:    The active destination can be indicated with a pill shape in a
           contrasting color
Name:      Bottom navigation has been renamed navigation bar

M2: A drop shadow indicates placement on top of content. Filled and regular
    weight icons indicate active states.
M3: Taller and no drop shadow. Filled icons and an active indicator indicate
    active state.
```

## Navigation rail — Overview

```text
Use navigation rails in medium, expanded, large, or extra-large window sizes
Can contain 3-7 destinations plus an optional FAB
Always put the rail in the same place, even on different screens of an app

Collapsed and expanded navigation rails can transition between each other on any
device, including:
  1. Large or medium window size classes like tablets
  2. Compact window size classes like phones in portrait orientation
```

### M3 Expressive update (May 2025)

```text
A collapsed and expanded navigation rail have been introduced to replace the
baseline nav rail. The expanded nav rail is meant to replace the navigation
drawer.

Variants and naming:
  The baseline navigation rail is no longer recommended
  Added two wider navigation rails:
    Collapsed: replaces baseline nav rail
    Expanded:  replaces navigation drawer

Configurations:
  Expanded rail modality:
    Non-modal
    Modal
  Expanded behavior:
    Transition to collapsed navigation rail
    Hide when collapsed

Color:
  Active label on vertical items changed from on surface variant to secondary

The collapsed and expanded navigation rails match visually and can transition
into each other
```

### Differences from M2

```text
Behavior: Predictive back interaction
Color:    New color mappings and compatibility with dynamic color
States:   The active destination can be indicated with a pill shape in a
          contrasting color

M2: The navigation rail uses icon color, weight, and fill to communicate which
    destination is active
M3: The navigation rail uses a pill-shaped active indicator to communicate which
    destination is active
```

## 출처

- <https://m3.material.io/components/navigation-bar/overview>
- <https://m3.material.io/components/navigation-rail/overview>

---

## Token tables (추출, 2026-10-04)

`tools/generators/fetch_m3_component_tokens.py`로 각 컴포넌트의 `TOKEN_TABLE.<hash>.json`을
읽은 결과. specs 페이지의 "Tokens & specs" 절은 CSR로 그려지므로 사람이 복사하면 참조 열이
사라진다 — 그래서 값이 아니라 **참조 사슬**이 남아 있는 이 형태로 기록한다.

URL은 페이지마다 content-hash가 달라 관측해야 한다. dsdb 38.2.9, table revision
2026-05-27T22:44:15.009106Z:

```txt
navigation bar   .../dsdb-m3/2026-09-23_06-10-05/TOKEN_TABLE.52865380210a9b69.json
navigation rail  .../dsdb-m3/2026-09-23_06-10-05/TOKEN_TABLE.5b55bd7cc1ea6e29.json
```

각 표는 **두 네임스페이스를 함께 싣는다**: `md.comp.nav-bar` / `md.comp.nav-rail`이
Expressive의 flexible·collapsed/expanded 형태이고, `md.comp.navigation-bar` /
`md.comp.navigation-rail`이 비권장 baseline이다. 구현은 전자를 쓴다.

```txt
component: Navigation bar   dsdb 38.2.9
table revision: 2026-05-27T22:44:15.009106Z

[md.comp.nav-bar]
  md.comp.nav-bar.container.color                                    COLOR         -> md.sys.color.surface-container
  md.comp.nav-bar.container.elevation                                ELEVATION     -> md.sys.elevation.level2
  md.comp.nav-bar.container.height                                   LENGTH        {"value":64,"unit":"DIPS"}
  md.comp.nav-bar.container.shadow-color                             COLOR         -> md.sys.color.shadow
  md.comp.nav-bar.container.shape                                    SHAPE         -> md.sys.shape.corner.none
  md.comp.nav-bar.item.active-indicator.icon-label-space             LENGTH        {"value":4,"unit":"DIPS"}
  md.comp.nav-bar.item.active-indicator.shape                        SHAPE         -> md.sys.shape.corner.full
  md.comp.nav-bar.item.active.focused.state-layer.color              COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-bar.item.active.focused.state-layer.opacity            OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.nav-bar.item.active.hovered.state-layer.color              COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-bar.item.active.hovered.state-layer.opacity            OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.nav-bar.item.active.icon.color                             COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-bar.item.active.indicator.color                        COLOR         -> md.sys.color.secondary-container
  md.comp.nav-bar.item.active.label-text.color                       COLOR         -> md.sys.color.secondary
  md.comp.nav-bar.item.active.pressed.state-layer.color              COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-bar.item.active.pressed.state-layer.opacity            OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.nav-bar.item.between-space                                 LENGTH        -> md.sys.measurement.space0
  md.comp.nav-bar.item.horizontal.active-indicator.height            LENGTH        {"value":40,"unit":"DIPS"}
  md.comp.nav-bar.item.horizontal.active-indicator.icon-label-space  LENGTH        -> md.sys.measurement.space50
  md.comp.nav-bar.item.horizontal.active-indicator.leading-space     LENGTH        -> md.sys.measurement.space200
  md.comp.nav-bar.item.horizontal.active-indicator.trailing-space    LENGTH        -> md.sys.measurement.space200
  md.comp.nav-bar.item.horizontal.label-text.font                    TYPOGRAPHY    -> md.sys.typescale.label-medium
  md.comp.nav-bar.item.icon.size                                     LENGTH        {"value":24,"unit":"DIPS"}
  md.comp.nav-bar.item.inactive.focused.state-layer.color            COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-bar.item.inactive.hovered.state-layer.color            COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-bar.item.inactive.icon.color                           COLOR         -> md.sys.color.on-surface-variant
  md.comp.nav-bar.item.inactive.label-text.color                     COLOR         -> md.sys.color.on-surface-variant
  md.comp.nav-bar.item.inactive.pressed.state-layer.color            COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-bar.item.vertical.active-indicator.height              LENGTH        {"value":32,"unit":"DIPS"}
  md.comp.nav-bar.item.vertical.active-indicator.icon-label-space    LENGTH        -> md.sys.measurement.space50
  md.comp.nav-bar.item.vertical.active-indicator.width               LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.nav-bar.item.vertical.container.between-space              LENGTH        -> md.sys.measurement.space75
  md.comp.nav-bar.item.vertical.label-text.font                      TYPOGRAPHY    -> md.sys.typescale.label-medium
[md.comp.navigation-bar]
  md.comp.navigation-bar.active-indicator.color                      COLOR         -> md.sys.color.secondary-container
  md.comp.navigation-bar.active-indicator.height                     LENGTH        {"value":32,"unit":"DIPS"}
  md.comp.navigation-bar.active-indicator.shape                      SHAPE         -> md.sys.shape.corner.full
  md.comp.navigation-bar.active-indicator.width                      LENGTH        {"value":64,"unit":"DIPS"}
  md.comp.navigation-bar.active.focus.icon.color                     COLOR         -> md.sys.color.on-secondary-container
  md.comp.navigation-bar.active.focus.label-text.color               COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.active.focus.state-layer.color              COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.active.hover.icon.color                     COLOR         -> md.sys.color.on-secondary-container
  md.comp.navigation-bar.active.hover.label-text.color               COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.active.hover.state-layer.color              COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.active.icon.color                           COLOR         -> md.sys.color.on-secondary-container
  md.comp.navigation-bar.active.label-text.color                     COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.active.label-text.weight                    FONT_WEIGHT   -> md.sys.typescale.label-medium.weight.prominent
  md.comp.navigation-bar.active.pressed.icon.color                   COLOR         -> md.sys.color.on-secondary-container
  md.comp.navigation-bar.active.pressed.label-text.color             COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.active.pressed.state-layer.color            COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.container.color                             COLOR         -> md.sys.color.surface-container
  md.comp.navigation-bar.container.elevation                         ELEVATION     -> md.sys.elevation.level2
  md.comp.navigation-bar.container.height                            LENGTH        {"value":80,"unit":"DIPS"}
  md.comp.navigation-bar.container.shape                             SHAPE         -> md.sys.shape.corner.none
  md.comp.navigation-bar.focus.indicator.color                       COLOR         -> md.sys.color.secondary
  md.comp.navigation-bar.focus.indicator.outline.offset              LENGTH        -> md.sys.state.focus-indicator.inner-offset
  md.comp.navigation-bar.focus.indicator.thickness                   LENGTH        -> md.sys.state.focus-indicator.thickness
  md.comp.navigation-bar.focus.state-layer.opacity                   OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.navigation-bar.hover.state-layer.opacity                   OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.navigation-bar.icon.size                                   LENGTH        {"value":24,"unit":"DIPS"}
  md.comp.navigation-bar.inactive.focus.icon.color                   COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.inactive.focus.label-text.color             COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.inactive.focus.state-layer.color            COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.inactive.hover.icon.color                   COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.inactive.hover.label-text.color             COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.inactive.hover.state-layer.color            COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.inactive.icon.color                         COLOR         -> md.sys.color.on-surface-variant
  md.comp.navigation-bar.inactive.label-text.color                   COLOR         -> md.sys.color.on-surface-variant
  md.comp.navigation-bar.inactive.pressed.icon.color                 COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.inactive.pressed.label-text.color           COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.inactive.pressed.state-layer.color          COLOR         -> md.sys.color.on-surface
  md.comp.navigation-bar.label-text.font                             FONT_NAMES    -> md.sys.typescale.label-medium.font
  md.comp.navigation-bar.label-text.line-height                      LINE_HEIGHT   -> md.sys.typescale.label-medium.line-height
  md.comp.navigation-bar.label-text.size                             FONT_SIZE     -> md.sys.typescale.label-medium.size
  md.comp.navigation-bar.label-text.tracking                         FONT_TRACKING  -> md.sys.typescale.label-medium.tracking
  md.comp.navigation-bar.label-text.type                             TYPOGRAPHY    {"fontNameTokenName":"md.comp.navigation-bar.label-text.font","fontWeightTokenName":"md.comp.navigation-bar.label-text.weight","fontSizeTokenName":"md.comp.navigation-bar.label-text.size","fontTrackingTokenName":"md.comp.navigation-bar.label-text.tracking","lineHeightTokenName":"md.comp.navigation-bar.label-text.line-height"}
  md.comp.navigation-bar.label-text.weight                           FONT_WEIGHT   -> md.sys.typescale.label-medium.weight
  md.comp.navigation-bar.pressed.state-layer.opacity                 OPACITY       -> md.sys.state.pressed.state-layer-opacity
[md.ref.palette]
[md.ref.typeface]
[md.sys.color]
[md.sys.elevation]
[md.sys.measurement]
[md.sys.shape]
[md.sys.state]
[md.sys.typescale]

included: 149
skipped (deprecated): 10
  - md.comp.navigation-bar.badge.color
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.*` tokens.
  - md.comp.navigation-bar.badge.shape
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.*` tokens.
  - md.comp.navigation-bar.badge.size
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.*` tokens.
  - md.comp.navigation-bar.container.shadow-color
      Bug: should not have been created. Remove any shadows on navigation bars.
  - md.comp.navigation-bar.container.surface-tint-layer.color
      Deprecated as part of the update from opacity based surfaces to tonal surfaces. Surfaces no longer use surface-tint layers for tinting, please use the desired surface role directly as the container color.
  - md.comp.navigation-bar.large-badge.color
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-bar.large-badge.shape
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-bar.large-badge.size
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.sys.color.surface-tint
      This token should no longer be used and its function/usage has been replaced by the tonal surface colors
  - md.sys.typescale.label-medium.weight.prominent
      No longer in user. Please use emphasized token instead
```

```txt
component: Navigation rail   dsdb 38.2.9
table revision: 2026-05-27T22:44:15.009106Z

[md.comp.nav-rail]
  md.comp.nav-rail.collapsed.container.color                  COLOR         -> md.sys.color.surface
  md.comp.nav-rail.collapsed.container.elevation              ELEVATION     -> md.sys.elevation.level0
  md.comp.nav-rail.collapsed.container.shape                  SHAPE         -> md.sys.shape.corner.none
  md.comp.nav-rail.collapsed.container.width                  LENGTH        {"value":96,"unit":"DIPS"}
  md.comp.nav-rail.collapsed.item.vertical-space              LENGTH        -> md.sys.measurement.space50
  md.comp.nav-rail.collapsed.narrow.container.width           LENGTH        {"value":80,"unit":"DIPS"}
  md.comp.nav-rail.collapsed.top-space                        LENGTH        {"value":44,"unit":"DIPS"}
  md.comp.nav-rail.expanded.container.color                   COLOR         -> md.sys.color.surface
  md.comp.nav-rail.expanded.container.elevation               ELEVATION     -> md.sys.elevation.level0
  md.comp.nav-rail.expanded.container.shape                   SHAPE         -> md.sys.shape.corner.none
  md.comp.nav-rail.expanded.container.width.maximum           LENGTH        {"value":360,"unit":"DIPS"}
  md.comp.nav-rail.expanded.container.width.minimum           LENGTH        {"value":220,"unit":"DIPS"}
  md.comp.nav-rail.expanded.modal.container.color             COLOR         -> md.sys.color.surface-container
  md.comp.nav-rail.expanded.modal.container.elevation         ELEVATION     -> md.sys.elevation.level2
  md.comp.nav-rail.expanded.modal.container.shape             SHAPE         -> md.sys.shape.corner.large
  md.comp.nav-rail.expanded.top-space                         LENGTH        {"value":44,"unit":"DIPS"}
  md.comp.nav-rail.item.active-indicator.icon-label-space     LENGTH        -> md.sys.measurement.space100
  md.comp.nav-rail.item.active-indicator.leading-space        LENGTH        -> md.sys.measurement.space200
  md.comp.nav-rail.item.active-indicator.shape                SHAPE         -> md.sys.shape.corner.full
  md.comp.nav-rail.item.active-indicator.trailing-space       LENGTH        -> md.sys.measurement.space200
  md.comp.nav-rail.item.active.focused.state-layer.color      COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-rail.item.active.focused.state-layer.opacity    OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.nav-rail.item.active.hovered.state-layer.color      COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-rail.item.active.hovered.state-layer.opacity    OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.nav-rail.item.active.icon.color                     COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-rail.item.active.indicator.color                COLOR         -> md.sys.color.secondary-container
  md.comp.nav-rail.item.active.label-text.color               COLOR         -> md.sys.color.secondary
  md.comp.nav-rail.item.active.pressed.state-layer.color      COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-rail.item.active.pressed.state-layer.opacity    OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.nav-rail.item.container.height                      LENGTH        {"value":64,"unit":"DIPS"}
  md.comp.nav-rail.item.container.shape                       SHAPE         -> md.sys.shape.corner.none
  md.comp.nav-rail.item.container.vertical-space              LENGTH        -> md.sys.measurement.space75
  md.comp.nav-rail.item.header-space-minimum                  LENGTH        -> md.sys.measurement.space500
  md.comp.nav-rail.item.horizontal.active-indicator.height    LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.nav-rail.item.horizontal.full-width.leading-space   LENGTH        -> md.sys.measurement.space200
  md.comp.nav-rail.item.horizontal.full-width.trailing-space  LENGTH        -> md.sys.measurement.space200
  md.comp.nav-rail.item.horizontal.icon-label-space           LENGTH        -> md.sys.measurement.space100
  md.comp.nav-rail.item.horizontal.label-text.font            TYPOGRAPHY    -> md.sys.typescale.label-large
  md.comp.nav-rail.item.icon.size                             LENGTH        {"value":24,"unit":"DIPS"}
  md.comp.nav-rail.item.inactive.focused.state-layer.color    COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-rail.item.inactive.hovered.state-layer.color    COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-rail.item.inactive.icon.color                   COLOR         -> md.sys.color.on-surface-variant
  md.comp.nav-rail.item.inactive.label-text.color             COLOR         -> md.sys.color.on-surface-variant
  md.comp.nav-rail.item.inactive.pressed.state-layer.color    COLOR         -> md.sys.color.on-secondary-container
  md.comp.nav-rail.item.short.container.height                LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.nav-rail.item.vertical.active-indicator.height      LENGTH        {"value":32,"unit":"DIPS"}
  md.comp.nav-rail.item.vertical.active-indicator.width       LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.nav-rail.item.vertical.icon-label-space             LENGTH        -> md.sys.measurement.space50
  md.comp.nav-rail.item.vertical.label-text.font              TYPOGRAPHY    -> md.sys.typescale.label-medium
  md.comp.nav-rail.item.vertical.leading-space                LENGTH        -> md.sys.measurement.space200
  md.comp.nav-rail.item.vertical.trailing-space               LENGTH        -> md.sys.measurement.space200
[md.comp.navigation-rail]
  md.comp.navigation-rail.active-indicator.color              COLOR         -> md.sys.color.secondary-container
  md.comp.navigation-rail.active-indicator.height             LENGTH        {"value":32,"unit":"DIPS"}
  md.comp.navigation-rail.active-indicator.shape              SHAPE         -> md.sys.shape.corner.full
  md.comp.navigation-rail.active-indicator.width              LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.navigation-rail.active.focus.icon.color             COLOR         -> md.sys.color.on-secondary-container
  md.comp.navigation-rail.active.focus.label-text.color       COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.active.focus.state-layer.color      COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.active.hover.icon.color             COLOR         -> md.sys.color.on-secondary-container
  md.comp.navigation-rail.active.hover.label-text.color       COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.active.hover.state-layer.color      COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.active.icon.color                   COLOR         -> md.sys.color.on-secondary-container
  md.comp.navigation-rail.active.label-text.color             COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.active.label-text.weight            FONT_WEIGHT   -> md.sys.typescale.label-medium.weight.prominent
  md.comp.navigation-rail.active.pressed.icon.color           COLOR         -> md.sys.color.on-secondary-container
  md.comp.navigation-rail.active.pressed.label-text.color     COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.active.pressed.state-layer.color    COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.container.color                     COLOR         -> md.sys.color.surface
  md.comp.navigation-rail.container.elevation                 ELEVATION     -> md.sys.elevation.level0
  md.comp.navigation-rail.container.shape                     SHAPE         -> md.sys.shape.corner.none
  md.comp.navigation-rail.container.width                     LENGTH        {"value":80,"unit":"DIPS"}
  md.comp.navigation-rail.focus.state-layer.opacity           OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.navigation-rail.hover.state-layer.opacity           OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.navigation-rail.icon.size                           LENGTH        {"value":24,"unit":"DIPS"}
  md.comp.navigation-rail.inactive.focus.icon.color           COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.inactive.focus.label-text.color     COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.inactive.focus.state-layer.color    COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.inactive.hover.icon.color           COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.inactive.hover.label-text.color     COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.inactive.hover.state-layer.color    COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.inactive.icon.color                 COLOR         -> md.sys.color.on-surface-variant
  md.comp.navigation-rail.inactive.label-text.color           COLOR         -> md.sys.color.on-surface-variant
  md.comp.navigation-rail.inactive.pressed.icon.color         COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.inactive.pressed.label-text.color   COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.inactive.pressed.state-layer.color  COLOR         -> md.sys.color.on-surface
  md.comp.navigation-rail.label-text.font                     FONT_NAMES    -> md.sys.typescale.label-medium.font
  md.comp.navigation-rail.label-text.line-height              LINE_HEIGHT   -> md.sys.typescale.label-medium.line-height
  md.comp.navigation-rail.label-text.size                     FONT_SIZE     -> md.sys.typescale.label-medium.size
  md.comp.navigation-rail.label-text.tracking                 FONT_TRACKING  -> md.sys.typescale.label-medium.tracking
  md.comp.navigation-rail.label-text.type                     TYPOGRAPHY    {"fontNameTokenName":"md.comp.navigation-rail.label-text.font","fontWeightTokenName":"md.comp.navigation-rail.label-text.weight","fontSizeTokenName":"md.comp.navigation-rail.label-text.size","fontTrackingTokenName":"md.comp.navigation-rail.label-text.tracking","lineHeightTokenName":"md.comp.navigation-rail.label-text.line-height"}
  md.comp.navigation-rail.label-text.weight                   FONT_WEIGHT   -> md.sys.typescale.label-medium.weight
  md.comp.navigation-rail.no-label.active-indicator.height    LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.navigation-rail.no-label.active-indicator.shape     SHAPE         -> md.sys.shape.corner.full
  md.comp.navigation-rail.pressed.state-layer.opacity         OPACITY       -> md.sys.state.pressed.state-layer-opacity
[md.ref.palette]
[md.ref.typeface]
[md.sys.color]
[md.sys.elevation]
[md.sys.measurement]
[md.sys.shape]
[md.sys.state]
[md.sys.typescale]

included: 187
skipped (deprecated): 31
  - md.comp.nav-rail.expanded.between-item-space
      Token not needed as value is "0dp"
  - md.comp.nav-rail.expanded.vertical.trailing-space
      Token moved to nav rail item horizontal set
  - md.comp.navigation-rail.badge.color
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.*` tokens.
  - md.comp.navigation-rail.badge.shape
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.*` tokens.
  - md.comp.navigation-rail.badge.size
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.*` tokens.
  - md.comp.navigation-rail.label-text.font-family
      For consistency, we have standardized typography naming. Replace usage with the suggested token.
  - md.comp.navigation-rail.label-text.font-size
      For consistency, we have standardized typography naming. Replace usage with the suggested token.
  - md.comp.navigation-rail.label-text.letter-spacing
      For consistency, we have standardized typography naming. Replace usage with the suggested token.
  - md.comp.navigation-rail.large-badge-label.color
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-rail.large-badge-label.font
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-rail.large-badge-label.font-family
      For consistency, we have standardized typography naming. Replace usage with the suggested token.
  - md.comp.navigation-rail.large-badge-label.line-height
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-rail.large-badge-label.size
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-rail.large-badge-label.tracking
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-rail.large-badge-label.type
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-rail.large-badge-label.weight
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-rail.large-badge.color
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-rail.large-badge.shape
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-rail.large-badge.size
      Badge values were refactored out into their own token set. Replace usage with the equivalent `md.comp.badge.large.*` tokens.
  - md.comp.navigation-rail.menu.focus.icon.color
      Using menu button token instead, this token no longer needed.
  - md.comp.navigation-rail.menu.focus.state-layer.color
      Using menu button token instead, this token no longer needed.
  - md.comp.navigation-rail.menu.focus.state-layer.opacity
      Using menu button token instead, this token no longer needed.
  - md.comp.navigation-rail.menu.hover.icon.color
      Using menu button token instead, this token no longer needed.
  - md.comp.navigation-rail.menu.hover.state-layer.color
      Using menu button token instead, this token no longer needed.
  - md.comp.navigation-rail.menu.hover.state-layer.opacity
      Using menu button token instead, this token no longer needed.
  - md.comp.navigation-rail.menu.icon.color
      Using menu button token instead, this token no longer needed.
  - md.comp.navigation-rail.menu.icon.size
      Using menu button token instead, this token no longer needed.
  - md.comp.navigation-rail.menu.pressed.icon.color
      Using menu button token instead, this token no longer needed.
  - md.comp.navigation-rail.menu.pressed.state-layer.color
      Using menu button token instead, this token no longer needed.
  - md.comp.navigation-rail.menu.pressed.state-layer.opacity
      Using menu button token instead, this token no longer needed.
  - md.sys.typescale.label-medium.weight.prominent
      No longer in user. Please use emphasized token instead
```

