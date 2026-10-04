# Source: M3 Navigation bar / Navigation rail (raw)

/ 캡처 2026-10-03. `m3.material.io`는 JS로 본문을 그리기 때문에 `WebFetch`로는 제목만
/ 돌아온다. 아래는 브라우저로 렌더한 뒤 읽은 overview 본문이다. 다른 `SOURCE-M3-*-RAW.md`와
/ 같은 성격의 1차 자료이며, 해석은 `PLAN-FRONTEND-NAVIGATION-COMPONENTS.md`에 있다.
/
/ Specs / Guidelines / Accessibility 탭은 처음엔 캡처하지 않았다. **bar 쪽 세 탭은 이 문서
/ 끝에 2026-10-05에 추가했다** -- 호스트 컨테이너의 산술은 토큰이 아니라 그 prose에만 있다.
/ **rail 쪽 세 탭도 2026-10-05에 추가했다**(소유자가 붙여 준 본문). 두 호스트의 세 탭이
/ 모두 들어왔으므로 SLICING 문서가 남긴 캡처 미결은 닫혔다.

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


---

## Navigation bar — Specs / Guidelines / Accessibility (캡처 2026-10-05)

위 overview와 같은 방식으로 브라우저 렌더 후 읽었다. `DECISION-FRONTEND-NAVIGATION-COMPONENT-SLICING.md`
§6이 미결로 남긴 "Specs / Accessibility 표" 중 **bar 쪽만** 이것으로 닫힌다. rail의 세 탭은
아직 없다.

호스트(컨테이너)의 산술은 토큰이 아니라 이 prose에만 있다. item 쪽 문장은
`products/styleguide/_data/navigation_item.yml`이 이미 들고 있으므로 중복해서 적지 않고,
**bar가 소유하는 것만** 남긴다.

### Specs — Configurations / Measurements

```text
In compact windows, navigation bars use vertical items. In medium windows,
navigation bars should use horizontal items.

Category                 Configuration        M3           M3 Expressive
Navigation item layout   Vertical (default)   Available    Available
                         Horizontal           --           Available

Measurements:
  The navigation bar stretches the full window width.
  Vertical navigation items dynamically change width to equally fit the
  container. Horizontal navigation items have a fixed width, so extra space is
  added to the ends of the navigation bar instead.

  (도해 캡션) Navigation bar width and margins for compact and medium windows.
             Vertical navigation item / Margin from window edge /
             Horizontal navigation item

States: Enabled · Hovered (8% state layer) · Focused (10% state layer) ·
        Pressed (10% state layer)
```

### Guidelines — Usage / Anatomy / Adaptive design / Behavior

```text
Usage:
  Navigation bars provide access to three to five destinations. The nav bar is
  positioned at the bottom of windows for convenient access.
  One navigation destination is always active.
  Navigation bars shouldn't be used for accessing single tasks.
  For products with more than five navigation items, don't use a navigation bar;
  the elements may collide and there likely won't be enough space for translated
  text. Instead, consider using tabs ... or a modal expanded navigation rail.

  Don't: Avoid putting more than five navigation items in a navigation bar
  Don't: Don't remove the labels from navigation items
  Don't: Don't use a navigation bar for fewer than three destinations. Instead,
         use tabs.
  Don't: Navigation bar destinations have fixed positions. Don't scroll them or
         modify their positions.

Container:
  The container should always be placed at the bottom of the product and span
  the full length of the window. Navigation items are centered within the
  container.
  The container has a color fill to provide separation from other content.

Navigation items:
  Vertical items are best in compact windows, and horizontal items are best in
  medium windows.
  Horizontal items are centered in the nav bar with outer margins.
  (도해 캡션) The navigation bar is divided into equal-width segments with
             padding from the window edge

Adaptive design — Resizing:
  Only use navigation bars for compact and medium breakpoints.
  Compact:  For narrow windows, use a navigation bar or modal navigation rail.
  Medium:   Use a navigation bar or navigation rail. Decide based on whether
            horizontal or vertical space is more important.
  Expanded and extra-large: Use a navigation rail instead.
  The navigation bar container spans 100% of the window width.
  Don't: Don't use navigation bars for desktop layouts. Instead, use a
         navigation rail or tabs.

Adaptive design — Presentation:
  In medium breakpoints, use horizontal nav items to better use available space.
  Horizontal nav items should remain centered with the same padding at each
  breakpoint.

Behavior:
  Re-selecting the currently active destination should reset the scroll position
  to the top of the page.
  Swiping across the screen does not navigate between destinations, and is not
  supported by the navigation bar.
  Upon scroll, the navigation bar can appear or disappear.
  Don't hide the navigation bar on scroll when a screen reader is active.

Placement:
  The floating action button (FAB) is placed above the navigation bar.
  Navigation bars can be temporarily covered by dialogs, bottom sheets,
  navigation drawers, the on-screen keyboard ... They should not be permanently
  obstructed on any screen.
```

### Accessibility — 호스트 쪽만

```text
Text scaling and truncation:
  When someone sets their device to show a larger text size, the navigation bar
  should grow vertically to accommodate larger labels while retaining the
  default padding. It's okay for scaled text to wrap in navigation items.
  To remain accessible, ensure the full label is always visible on-screen at up
  to 2x text sizing. Beyond this size, text can truncate.

Labeling elements:
  A navigation bar's accessibility label can incorporate its adjacent UI text.
```

### 출처

- <https://m3.material.io/components/navigation-bar/specs>
- <https://m3.material.io/components/navigation-bar/guidelines>
- <https://m3.material.io/components/navigation-bar/accessibility>

---

## Navigation rail — Specs / Guidelines / Accessibility (캡처 2026-10-05)

소유자가 붙여 준 본문이다. `DECISION-FRONTEND-NAVIGATION-COMPONENT-SLICING.md` §6의
"Specs / Accessibility 표" 미결은 이것으로 **rail 쪽까지 닫힌다**.

item 쪽 문장은 `products/styleguide/_data/navigation_item.yml`이 이미 들고 있으므로 중복하지
않고, **rail이 소유하는 것과 bar와 갈라지는 것**을 남긴다.

MDC-Android의 `docs/components/NavigationRail.md`도 함께 전달됐다. 아직 읽지 않았으므로
여기에 인용하지 않는다 — 플랫폼 구현이 발행 스펙과 갈리는지 확인할 때 볼 자료다.
<https://github.com/material-components/material-components-android/blob/master/docs/components/NavigationRail.md>

### Overview

```text
Use navigation rails in medium, expanded, large, or extra-large window sizes
Can contain 3-7 destinations plus an optional FAB
Always put the rail in the same place, even on different screens of an app

Collapsed and expanded navigation rails can transition between each other on any
device, including:
  1. Large or medium window size classes like tablets
  2. Compact window size classes like phones in portrait orientation
```

### Specs — Variants / Configurations

```text
Variants:
  1. Collapsed navigation rail
  2. Expanded navigation rail

  Variant                      M3           M3 Expressive
  Collapsed navigation rail    --           Available
  Expanded navigation rail     --           Available
  Navigation rail (baseline)   Available    Not recommended. Use collapsed
                                            navigation rail.

Configurations:
  1. Expanded layout: standard
  2. Expanded layout: modal

  Category           Configuration         M3                            M3 Expressive
  Expanded layout    Standard (default)    Available as navigation drawer Available
                     Modal                 Available as navigation drawer Available
  Expanded behavior  Hide when collapsed   --                            Available

Token sets listed by the table's menu:
  Nav rail - Common / Collapsed / Expanded
  Nav rail item - Common / Vertical / Horizontal
```

### Specs — Anatomy / Color / States / Measurements

```text
Anatomy (collapsed and expanded):
  1. Container
  2. Menu (optional)
  3. FAB or Extended FAB (optional)
  4. Icon
  5. Active indicator
  6. Label text
  7. Large badge (optional)
  8. Large badge label (optional)
  9. Small badge (optional)

Color roles, in anatomy order:
  1. Surface container (optional)
  2. On secondary container
  3. Secondary container
  4. Secondary (vertical), On secondary container (horizontal)
  5. On surface variant
  6. On surface variant
  7. Error
  8. On error
  9. Error

States:
  The navigation item's target area always spans the full width of the nav rail,
  even if the item container hugs its contents.
  Enabled / Hovered / Focused / Pressed

Measurements:
  Navigation rail padding and size measurements
  Common layouts:
    1. Three navigation items
    2. Three navigation items with a menu
    3. Three navigation items with a FAB
    4. Three navigation items with a menu and FAB
```

### Guidelines — Usage

```text
The navigation rail can display navigation items, a menu, and a floating action
button (FAB) in a vertical orientation.

There are two variants, collapsed and expanded, which can easily transform into
each other when the menu button is selected.

Collapsed:
  The collapsed nav rail runs along the leading edge of the window, and should
  contain 3-7 navigation items. It should not be hidden.
  It can be used in medium to extra large breakpoints, such as tablets and
  desktop. In medium windows with few destinations, consider using a navigation
  bar instead. Compact windows should always use a navigation bar.

Expanded:
  The expanded navigation rail can be standard or modal, and should always open
  from a menu icon. An expanded rail can reveal secondary destinations not
  visible when collapsed.
  The standard configuration is placed beside body content. It's best for larger
  windows with lots of available space.
  The modal configuration overlaps the body content, and should be opened from a
  menu icon. Use the modal configuration for:
    - Information dense layouts where space is limited
    - Products with many navigation items
  In immersive experiences, the expanded navigation rail can be hidden entirely,
  appearing only when the menu icon is selected.
  The collapsed navigation rail should not be hidden.
```

### Guidelines — Anatomy sections

```text
Container:
  The navigation rail should be placed on the leading edge of the window. This is
  the left side for left-to-right languages, and the right side for
  right-to-left languages.
  The container fill can be turned off so the nav rail appears directly on the
  surface. When doing this, make sure all items have a minimum of 3:1 color
  contrast.
  The navigation rail should always run vertically along the side of a layout.
  Don't make it horizontal. Use a navigation bar for horizontal navigation.
  Don't: Don't use the navigation rail horizontally. Use a navigation bar
         instead.
  Navigation rail items can be aligned as a group to the top or center of a
  layout. On tablets, use center alignment to make it easier to reach items.
  The menu icon and FAB should always be top-aligned.

Menu (optional):
  The menu button can transition between the collapsed and expanded navigation
  rails. Once expanded, the rail can reveal secondary destinations.
  When the navigation rail is expanded, the menu icon should change to represent
  that it can be collapsed.

Floating action button (FAB) (optional):
  The container of the navigation rail is ideal for anchoring the FAB to the top
  of a screen, placing the app's key action above navigation destinations.
  When nested within another component, such as the navigation rail, the FAB's
  resting elevation should be level 0.
  Don't: Avoid placing the FAB below navigation items
  The top of the rail can also be used for a logo, however avoid using logos
  that could be mistaken as buttons.
  Don't: Don't use a logo as a menu button to expand the navigation rail.

Active indicator:
  Use the active indicator only for the current open page
  Don't: Don't use the active indicator for more than one navigation item at a
         time
  The active indicator hugs the label text in the expanded nav rail. To achieve
  a similar style to the baseline navigation drawer, consider modifying the
  active indicator to fill the container.
  The target area should always span the full width.

Icons:
  Navigation rail items must use icons that symbolize the content of their page.
  When a destination is selected, the icon fills and changes color. An active
  indicator appears behind the icon.

Label text:
  All navigation items require a one word label text.
  Avoid wrapping long labels when possible. If necessary, create a line break
  between words, or hyphenate longer words.
  Break up longer phrases into two text lines if necessary.
  Labels should be short enough to not be truncated. Don't shrink the type scale
  to fit longer text labels.
  Don't: Don't truncate or display an ellipsis in place of label text
  Don't: Don't reduce the type size to fit more characters into a destination
         label

Badges:
  In compact nav rails, the badge is placed in the upper right corner of the
  icon. In expanded nav rails, the badge should be placed next to the label
  text.

Divider (optional):
  A vertical divider can help separate the rail from app content. The divider
  should be positioned on the edge of the rail container that's adjacent to the
  app's content area.
```

### Guidelines — Placement / Adaptive design / Behavior

```text
Placement:
  In adaptive layouts, the navigation rail should be placed outside any panes,
  always along the leading edge of the window. Don't place it within body
  content.
  When the navigation rail is hidden, the body content can fill in the remaining
  space as long as the menu icon is still accessible.
  Tabs can be used alongside a navigation rail to create an extra layer of
  visible navigation.

Adaptive design — Resizing:
  When moving from a large screen to a small screen, a navigation rail can
  transform into a navigation bar, providing the same quick access in a
  configuration that's easier to use on smaller displays. NEVER USE THE
  NAVIGATION RAIL AND NAVIGATION BAR SIMULTANEOUSLY.
  Only use navigation rails for medium breakpoints and larger. If there are more
  than five destinations, consider using a modal expanded nav rail instead.
  Compact:  Don't use a standard navigation rail for compact layouts due to
            space constraints. Use a navigation bar instead.
  Medium:   Use a navigation rail, especially if prioritizing persistent
            vertical navigation over maximizing vertical content space.
  Expanded to extra-large: Use a navigation rail, not a navigation bar. Consider
            available horizontal space and the number of destinations when
            choosing between standard and modal.

Adaptive design — Presentation:
  When the navigation rail transitions from collapsed to expanded, the contents
  of the page should automatically adjust to fit.
  The contents of the navigation rail also expand to fill the space. For
  example, the FAB should transition into an extended FAB.
  Extra destinations can be shown in an expanded nav rail.

Behavior — Scrolling:
  Destinations in the navigation rail should remain visible and fixed when
  scrolling vertically.
  If a layout scrolls horizontally, the rail can scroll off-screen or remain
  fixed. To distinguish that content is scrolling underneath the rail, use a
  divider or add elevation to the rail.

Behavior — Selection:
  When a destination is tapped, the destination screen uses a top level
  transition pattern. In addition, the icon becomes filled and the active
  indicator expands from the center of the icon.

Behavior — Back:
  Predictive back only applies to the modal expanded navigation rail.
```

### Accessibility

```text
Interaction & style:
  The target area for expanded navigation rails spans the full width of the
  container, even though the active indicator visually hugs the content.
  If an icon doesn't have a filled style, use the semibold icon weight instead.

Text scaling and truncation:
  When someone sets their device to show a larger text size, the navigation rail
  items should grow vertically to accommodate larger labels while retaining the
  default padding. It's okay for scaled text to wrap in navigation items.
  To remain accessible, ensure the full label is always visible on-screen at up
  to 2x text sizing. Beyond this size, text can truncate.

Initial focus:
  Initial focus lands directly on the first interactive item, whether it's the
  menu, the FAB, or the first navigation item.
  From the FAB or menu, Tab brings the person to the navigation items. Tab or
  Arrows then navigate between items.

Keyboard navigation:
  Keys            Actions
  Tab / Arrows    Navigate between interactive elements
  Space / Enter   Selects an interactive element

Labeling elements:
  The accessibility label for a navigation item is typically the same as the
  adjacent text label.
```

### 출처

- <https://m3.material.io/components/navigation-rail/overview>
- <https://m3.material.io/components/navigation-rail/specs>
- <https://m3.material.io/components/navigation-rail/guidelines>
- <https://m3.material.io/components/navigation-rail/accessibility>
