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
