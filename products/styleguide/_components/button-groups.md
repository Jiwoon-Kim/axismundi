---
title: Button groups
description: 색이 없는 컨테이너, 선택을 소유하는 컨트롤, 그리고 스펙 두 문서가 서로 다른 말을 하는 지점
kind: material
order: 30
lang: ko
---

Button group은 **색 속성이 없습니다.** M3가 그렇게 씁니다 — "Button groups have
no color properties." 그렇다고 색을 생략한다는 뜻은 아닙니다. 색은 안에 든
**Button의 스펙**에서 옵니다. Standard는 실제 Button/Icon button 자식을 담으므로
`Filled`·`Tonal`·`Outlined`·`Elevated`가 자식의 속성입니다. Connected는 Figma에 색
프로퍼티가 없고, Segment가 독립 Button 스타일을 갖지 않습니다.

따라서 그룹 자체의 축은 variant, size, selection이고, 색은 Standard 자식의 Button
계약입니다. Connected의 기본 선택 색은 Button의 Filled toggle 역할을 소비할 뿐,
사용자가 고르는 Connected 색 축이 아닙니다.

같은 문단이 **쓰지 말아야 할 것 둘**도 지정합니다.

> Avoid using **standard icon buttons** or **text buttons**, as they have no
> container treatment.

둘 다 쉬는 상태에 container가 없다는 공통점을 가집니다. 그룹은 세그먼트가 서로
인접해 하나의 컨트롤로 읽혀야 하는데, 칠할 상자가 없으면 경계도 선택 표시도 설 자리가
없습니다. 그래서 Button의 다섯 색 스타일 중 **Text가 그룹에서 빠지는 이유는 두 개**입니다
— toggle Text Button이 M3에 없고, container가 없습니다.

**Elevated도 standard에서는 권장되지 않습니다.** M3 산문이 이름을 대는 것은
"filled, tonal, and outlined" 셋이고, Figma의 Standard button group `Color` 열거도
`Filled · Tonal · Outline` 셋입니다. Elevated는 그림에만 나옵니다. 이 페이지가
Elevated 표본을 남겨 두는 것은 **자식이 Button이라 기술적으로 가능하다는 사실**을
보이기 위해서이고, 권장한다는 뜻이 아닙니다.

## 자식이 무엇인지가 두 variant를 가릅니다

논증할 필요가 없었습니다. **Figma 프로퍼티 집합이 그대로 증거입니다.**

```
Standard button group          Connected button group
  type · size · color            type · size
  button type: icon | label      show 3rd segment
  show 5th button                └─ Segment 1
  └─ Icon button (togglable)     └─ Segment 2
  └─ Button                      └─ End segment
```

**Standard의 자식은 Button과 Icon button입니다** — 다른 곳에도 혼자 존재하는
컴포넌트이고, 각자 자기 프로퍼티를 전부 가집니다.

```
자식1  Icon button   type round · size large · width narrow · state enabled · icon
자식2  Button        type round · size large · state enabled · label text · show icon
```

**Connected의 자식은 Segment입니다.** 이 컴포넌트의 부품이지 독립 컴포넌트가
아니고, 프로퍼티가 다릅니다.

```
Segment   selected · state · show icon · icon · icon(selected) · show label text · label text
```

**Segment에는 type도 size도 color도 없습니다.** 그룹이 가집니다. 반대로 Standard의
자식은 그것들을 자기가 가집니다.

이 한 장의 대조가 앞의 논쟁을 끝냅니다.

| | 자식이 무엇인가 | type·size·color 소유 | 블록에서 |
|---|---|---|---|
| Standard | Button · Icon button 인스턴스 | 자식 | `allowedBlocks`로 기존 블록 재사용 |
| Connected | Segment | 그룹 | 이 그룹 전용 자식 |

그래서 **connected 세그먼트는 `core/button`이 될 수 없습니다.** 크기와 색을 가지면
안 되는데 `core/button`은 가집니다. 반대로 standard의 자식은 `core/button`이어야
합니다 — 이미 그 프로퍼티를 가진 블록이 있는데 새로 만들 이유가 없습니다.

`button type: icon | label`과 `show 5th button`은 런타임 속성이 아니라 **어떤 자식을
꽂을지 고르는 Figma의 저작 편의**입니다. 블록에서는 자식을 삽입하는 행위 자체가
그것입니다.

## 두 variant

| Variant | M3 | M3 Expressive | 인접 버튼 |
|---|---|---|---|
{% for v in site.data.button_group.variants -%}
| **{{ v.title }}** | {% if v.m3 == false %}—{% else %}{{ v.m3 }}{% endif %} | {% if v.expressive %}있음{% else %}—{% endif %} | {% if v.adjacent_interaction %}선택 시 폭·모양이 반응{% else %}반응 없음{% endif %} |
{% endfor %}

**Connected는 segmented button의 후신입니다.** Expressive가 segmented를 폐기하면서
그 자리를 가져갔고, 위 표의 M3 열이 "as segmented button"인 이유입니다.

두 variant의 차이는 기하가 아니라 **인접 버튼이 반응하는가**입니다.

```
standard    선택되거나 활성화된 버튼의 폭·모양·패딩이 바뀌고, 옆 버튼이 밀리며 폭이 바뀜
connected   선택된 버튼의 모양만 바뀜. 옆은 그대로
```

### standard는 선택 컨트롤만이 아닙니다

M3의 Buttons 쪽 설명이 이걸 분명히 합니다.

> A button group is a collection of buttons that relate to each other and can
> respond to one another. Both buttons and **icon buttons** can be used inside a
> button group. … Buttons with primary actions should have a higher visual
> emphasis through **size, color, or shape**.

예시가 미디어 플레이어입니다 — 작은 outlined 아이콘 버튼, 크고 넓은 tonal `Play`,
다시 작은 아이콘 버튼. **선택 상태가 없습니다.** 액션 셋이고, 크기와 색이 강조를
만듭니다.

guidelines의 문장도 같은 방향입니다 — "selected **or activated**", 그리고
"A selected **toggle** button also changes color". 토글이 아닌 버튼도 standard
안에 있고, 그건 활성화될 때 폭과 모양만 바뀝니다.

그래서 standard는 두 경우를 덮습니다.

```
액션 그룹      독립 버튼 N개, 크기·색으로 강조. 선택 없음   ← 미디어 플레이어
선택 컨트롤    토글 세그먼트, 단일/다중 선택
```

### 1. Standard, 선택 없는 액션 그룹

미디어 플레이어 예시입니다. 크기와 색이 다르고, **선택 상태가 없습니다.** 그리고
마크업이 `core/buttons`입니다 — 새 블록을 가정하지 않았습니다.

<div class="sg-demo">
  <div class="wp-block-buttons">
    <div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link wp-element-button" aria-label="Previous"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">skip_previous</span></button></div>
    <div class="wp-block-button is-style-tonal" data-size="medium"><button type="button" class="wp-block-button__link wp-element-button">Play</button></div>
    <div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link wp-element-button" aria-label="Next"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">skip_next</span></button></div>
  </div>
</div>

**이게 이 페이지의 결론을 화면으로 증명합니다.** 강조는 size·color·shape로 만들고,
셋 다 버튼의 속성입니다. 컨테이너는 배치만 합니다.

### Button 색 스타일은 standard에서 자식마다 고른다

아래 네 개는 [Buttons]({{ '/components/buttons/' | relative_url }})와 같은 Button
스펙을 그대로 씁니다. Filled는 기본값이라 클래스가 없고, 나머지는 WordPress가
내보내는 `is-style-*` variation입니다. **standard에서는 이 조합이 허용됩니다.**

<div class="sg-demo">
  <div class="wp-block-buttons">
    <div class="wp-block-button"><button type="button" class="wp-block-button__link wp-element-button">Filled</button></div>
    <div class="wp-block-button is-style-tonal"><button type="button" class="wp-block-button__link wp-element-button">Tonal</button></div>
    <div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link wp-element-button">Outlined</button></div>
    <div class="wp-block-button is-style-elevated"><button type="button" class="wp-block-button__link wp-element-button">Elevated</button></div>
  </div>
</div>

Text는 빠집니다. 이유가 둘입니다 — **toggle Text Button이 M3에 없고**, 그리고 M3가
container 없는 스타일을 그룹에서 쓰지 말라고 명시합니다. Elevated는 넣어 두었지만
standard의 권장 열거에는 없습니다. 위 서문의 단서를 함께 읽어야 합니다.

### 2. Standard, single-select

`List / Grid / Map`. 눌러 보세요 — 하나만 선택되고, **선택된 것을 다시 누르면
해제됩니다.**

<div class="sg-demo">
  <div class="wp-block-axismundi-button-group" data-variant="standard" data-size="small" data-selection="single" role="group" aria-label="View mode">
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">List</button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true">Grid</button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">Map</button>
  </div>
</div>

Lab의 Pattern A도 함께 남깁니다. 이쪽은 버튼을 흉내 내는 radio가 아니라, 실제
`input[type="radio"]`와 label입니다. 브라우저가 상호배타, 화살표 키, form reset을
소유합니다.

<div class="sg-demo">
  <fieldset class="wp-block-axismundi-button-group" data-variant="standard" data-size="small" aria-label="Native view mode">
    <legend class="screen-reader-text">Native view mode</legend>
    <input class="wp-block-axismundi-button-group__input" type="radio" name="standard-native-view" id="standard-native-list" />
    <label class="wp-block-axismundi-button-group__item wp-element-button" for="standard-native-list">List</label>
    <input class="wp-block-axismundi-button-group__input" type="radio" name="standard-native-view" id="standard-native-grid" checked />
    <label class="wp-block-axismundi-button-group__item wp-element-button" for="standard-native-grid">Grid</label>
    <input class="wp-block-axismundi-button-group__input" type="radio" name="standard-native-view" id="standard-native-map" />
    <label class="wp-block-axismundi-button-group__item wp-element-button" for="standard-native-map">Map</label>
  </fieldset>
</div>

### 3. Standard, multi-select

`Photos / Notes / Links`. 몇 개든 켤 수 있고, 전부 끌 수도 있습니다.

<div class="sg-demo">
  <div class="wp-block-axismundi-button-group" data-variant="standard" data-size="small" data-selection="multiple" role="group" aria-label="Filter">
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true">Photos</button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">Notes</button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true">Links</button>
  </div>
</div>

### 4. Connected, single-select-required

정확히 하나가 항상 선택됩니다. **선택된 것을 다시 눌러도 꺼지지 않습니다** — Theme
Switcher와 같은 모드입니다.

<div class="sg-demo">
  <div class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" data-selection="single" data-required="true" role="group" aria-label="Range">
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">Day</button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true">Week</button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">Month</button>
  </div>
</div>

Lab의 Connected Pattern A는 같은 상태를 native radio로도 보여줍니다. 여기서는
`name`을 공유하는 실제 radio가 required single-select를 보장합니다.

<div class="sg-demo">
  <fieldset class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" aria-label="Native density">
    <legend class="screen-reader-text">Native density</legend>
    <input class="wp-block-axismundi-button-group__input" type="radio" name="connected-native-density" id="connected-native-compact" />
    <label class="wp-block-axismundi-button-group__item wp-element-button" for="connected-native-compact">Compact</label>
    <input class="wp-block-axismundi-button-group__input" type="radio" name="connected-native-density" id="connected-native-comfort" checked />
    <label class="wp-block-axismundi-button-group__item wp-element-button" for="connected-native-comfort">Comfort</label>
    <input class="wp-block-axismundi-button-group__input" type="radio" name="connected-native-density" id="connected-native-spacious" />
    <label class="wp-block-axismundi-button-group__item wp-element-button" for="connected-native-spacious">Spacious</label>
  </fieldset>
</div>

**모서리가 움직이는 것이 shape morph입니다.** 쉬는 세그먼트는 안쪽 8px, 선택된 것은
캡슐, 누르는 동안은 4px입니다. 세 값이 전부 M3가 따로 발행한 것입니다.

**M3의 "50%"를 CSS `border-radius: 50%`로 쓰면 안 됩니다.** 그건 가로로 폭의 50%,
세로로 높이의 50%를 잡아서 — 세그먼트가 높이의 세 배쯤 넓으니 — **타원**이 됩니다.
그렇게 썼더니 정확히 타원이 나왔습니다. M3가 말하는 50%는 높이의 절반, 즉 캡슐이고,
그래서 계산된 길이(`calc(height / 2)`)로 구현합니다. axismundi-lab이 같은 번역에
도달해 있었습니다 — `--_button-group-pill-radius`, `components.css §28.9`.

길이여야 하는 이유가 하나 더 있습니다. `--md-sys-shape-corner-full`(9999px)로 쓰면
[Shape]({{ '/foundations/shape/' | relative_url }})가 경고한 그대로 됩니다 —
브라우저가 상자에 맞춰 줄이므로 전환 내내 pill로 보이다가 끝에서 튑니다.

### 5. Connected 기하를 쓴 내비게이션 — M3가 피하라는 경우

**이건 모범이 아니라 현재 상태입니다.** 밀도 전환이 실제로 이 모양이고, M3는 이걸
하지 말라고 씁니다.

> Avoid using a connected group when none of the buttons can be toggled

세그먼트가 `<a href>` 링크입니다. 토글이 아니라 서버 내비게이션이고, 선택 표시가
`aria-current="page"`인 것도 그래서입니다 — 링크의 현재 상태는 서버의 답이지 클릭
핸들러의 것이 아닙니다. **클릭해도 여기서는 바뀌지 않습니다.**

M3 안에서도 긴장이 있습니다. connected의 용도로 "select options, **switch views**,
or sort elements"를 들면서, 동시에 토글이 아니면 쓰지 말라고 합니다. 뷰 전환을
클라이언트 토글로 구현한다는 전제이고, 서버 렌더링을 다루지 않습니다.

색은 이 문서의 Connected 기본값(Filled toggle 역할)을 씁니다. 이 표본의 문제는
색이 아니라 **토글이 아니라는 점** 하나입니다.

<div class="sg-demo">
  <nav class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" aria-label="Entry density">
    <a class="wp-block-axismundi-button-group__item wp-element-button" href="#1">Card</a>
    <a class="wp-block-axismundi-button-group__item wp-element-button" href="#2" aria-current="page">List</a>
    <a class="wp-block-axismundi-button-group__item wp-element-button" href="#3">Compact</a>
  </nav>
</div>

## 그룹 크기와 버튼 크기가 겹칩니다

M3는 양쪽에 size를 발행합니다 — Button에 다섯, Button group에도 다섯. 컨테이너가
자기 크기를 갖는데 개별 버튼도 크기를 가지면, 그룹 크기는 무엇인가.

스펙이 세 문장으로 답합니다.

```
"By default, all buttons in a standard group should be the same size"
"Only use multiple sizes in a group for hero moments"
"Button groups adapt to the height of the buttons inside"
```

**그룹 크기는 기본값이고, 선언한 버튼이 이깁니다.** 컨테이너 높이는 정해지는 값이
아니라 안에 든 것을 따라가는 값입니다.

**단, standard에서만입니다.** connected의 Segment에는 size 프로퍼티가 없으니 덮을
것도 없습니다 — 위 대조표가 그 이야기입니다.

CSS에서는 장치가 필요 없습니다. 그룹이 자기 자신에 `--ax-button-*`를 걸면 상속되고,
세그먼트가 자기 것을 선언하면 요소 자신에 걸린 쪽이 이깁니다. 높이도 규칙이 필요
없습니다 — flex 행이라 이미 가장 큰 자식만큼 높습니다.

<div class="sg-demo">
  <div class="wp-block-axismundi-button-group" data-variant="standard" data-size="small" data-selection="single" role="group" aria-label="Hero size">
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">Cancel</button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" data-size="medium" aria-pressed="true">Continue</button>
  </div>
</div>

그룹은 small인데 `Continue`만 medium입니다. 실측 40 / 56이고 그룹 높이는 56입니다.

**between space는 따라가지 않습니다.** 그룹의 값으로 남습니다 — 그룹 토큰이고, hero
버튼은 그룹 안의 예외이지 새 그룹 크기가 아니기 때문입니다.

여기에 대가가 하나 있고 M3가 답하지 않습니다. **XS와 S의 넉넉한 between space는
48dp 타깃을 사기 위한 것**이고, M3는 그 여백을 줄이지 말라고 합니다. 그런데 M으로
선언한 그룹에 XS 세그먼트를 넣으면 그 버튼은 M의 8dp 이웃을 받습니다. 큰 여백이 사던
타깃이 사라집니다. **작은 쪽으로 섞을 때만 문제이고, 큰 쪽으로 섞는 것은 무료입니다.**

## 아이콘과 라벨의 극성이 뒤집혀 있습니다

Figma의 Button 프로퍼티는 이렇습니다.

```
Label text     Label            항상 있음
Show icon      boolean          선택
Icon           stars_filled
```

**버튼은 라벨이 필수이고 아이콘이 선택입니다.** 아이콘만 남는 것은 Button이 아니라
Icon button이니까요.

그룹은 반대입니다. 세그먼트가 아이콘을 지니고, **라벨을 보일지는 그룹이 정합니다.**
`axismundi/theme-switcher`가 이미 그 모양입니다.

```
Button              labelText  +  showIcon        버튼마다
Connected Segment   icon       +  show label text  세그먼트마다
```

단수와 복수가 그 차이를 그대로 말합니다 — `showIcon`은 한 Button의 것이고,
`Show label text`는 Connected Segment의 것입니다. Connected group의 Figma 프로퍼티는
`Show label text`를 세그먼트마다 둡니다.

```
Segment 1 / Segment 2 / End segment
  Selected · State · Show icon · Icon · Icon(selected) · Show label text · Label text
```

Figma의 고정 Segment 슬롯에서는 한 버튼만 icon-only로 둘 수 있습니다. M3 layout 목록의
**"Label buttons and icon buttons"**도 바로 그 혼합을 보여줍니다. 테마스위처의
`showLabels`는 세 항목이 함께 바뀌는 별도 Axismundi UX 결정이지, Connected의 M3
Segment 속성을 대체하지 않습니다.

**라벨 요소는 사라지지 않습니다.** 플러그인이 하는 일은 클래스를 하나 더하는 것뿐이고,
그래서 접근 가능한 이름의 출처가 보이든 안 보이든 하나입니다.

```php
$label_class = $show_labels
    ? 'axismundi-theme-switcher__label'
    : 'axismundi-theme-switcher__label screen-reader-text';
```

<div class="sg-demo sg-demo--stack">
  <div class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" data-selection="single" data-required="true" role="group" aria-label="Labels visible">
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">contrast</span>Auto</button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">light_mode</span>Light</button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">dark_mode</span>Dark</button>
  </div>
  <div class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" data-selection="single" data-required="true" role="group" aria-label="Labels hidden">
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" data-labels="hidden" aria-pressed="true"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">contrast</span><span class="screen-reader-text">Auto</span></button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" data-labels="hidden" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">light_mode</span><span class="screen-reader-text">Light</span></button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" data-labels="hidden" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">dark_mode</span><span class="screen-reader-text">Dark</span></button>
  </div>
</div>

같은 세 세그먼트이고 각 Segment의 `Show label text`만 false입니다. 아래 줄에서도
`Auto`·`Light`·`Dark`는 DOM에 그대로 있습니다.

라벨이 없어지면 좌우 여백도 할 일이 없어집니다. 다만 그 자리를 무엇이 채우는지는
variant마다 다릅니다 — **connected는 계속 늘어납니다.** "span the width of the page or
surface it's placed on"이니 라벨 유무와 무관합니다. 실측 252×40으로 변하지 않습니다.

standard는 줄어듭니다. 다만 **정사각형이 되지는 않습니다 — 실측 48×40입니다.** 높이에서
끌어온 40px보다 48dp 타깃 하한이 먼저 이기기 때문입니다. 이건 규칙이 제 일을 한 것이고,
동시에 남은 문제를 드러냅니다. **높이 40px은 그대로라 48×48이 아닙니다.** 시각 상자를
넓히는 것만으로는 타깃이 완성되지 않고, 타깃을 상자 밖으로 확장하는 별도 처리가
필요합니다. 지금은 없습니다.

<div class="sg-demo">
  <div class="wp-block-axismundi-button-group" data-variant="standard" data-size="small" data-selection="single" role="group" aria-label="Standard icon-only">
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" data-labels="hidden" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">format_bold</span><span class="screen-reader-text">Bold</span></button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" data-labels="hidden" aria-pressed="true"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">format_italic</span><span class="screen-reader-text">Italic</span></button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" data-labels="hidden" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">format_underlined</span><span class="screen-reader-text">Underline</span></button>
  </div>
</div>

## Icon(selected)는 두 번째 아이콘이 아닙니다

세그먼트 프로퍼티에 아이콘이 둘 있습니다.

```
Icon            stars
Icon(selected)  stars_filled
```

**Figma는 결과를 서술한 것입니다.** 정적 파일에서 채워진 심볼을 보이려면 다른 글리프로
바꾸는 수밖에 없습니다.

웹에서는 글리프를 바꾸지 않습니다. Material Symbols는 가변 폰트이고 `FILL`이 그 축
중 하나라, **축 값을 바꿉니다.** 테마가 그 축을 `@property`로 등록해 두었기 때문에
스냅이 아니라 보간됩니다.

```css
@property --md-icon-fill { syntax: "<number>"; inherits: true; initial-value: 0; }

.…__item[aria-pressed="true"] .material-symbols-outlined { --md-icon-fill: 1; }
```

마크업의 아이콘 이름은 하나이고, 렌더되는 상태가 둘입니다. `axismundi/theme-switcher`가
이미 이렇게 합니다.

hover도 채웁니다. 단 **포인터가 있을 때만**이고, 선택 쪽은 조건 없이 채웁니다 — 둘 다
게이팅하면 터치스크린에서 선택된 세그먼트가 안 채워지는데, 거기가 fill이 실제로 일을
하는 유일한 자리입니다.

<div class="sg-demo">
  <div class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" data-selection="single" data-required="true" role="group" aria-label="Icon fill">
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">star</span>One</button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">star</span>Two</button>
    <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">star</span>Three</button>
  </div>
</div>

세 세그먼트의 아이콘 이름이 전부 `star`입니다. 선택된 것만 채워져 있고, 눌러 옮기면
채움이 따라옵니다.

## 선택 모델이 두 문서에서 어긋납니다

이 페이지가 존재하는 진짜 이유입니다.

**Specs**는 selection을 radio/checkbox 언어로 씁니다.

| Category | Option | M3 | M3 Expressive |
|---|---|---|---|
{% for c in site.data.button_group.configurations -%}
| {{ c.category }} | {{ c.option }} | {% if c.m3 == false %}—{% else %}{{ c.m3 }}{% endif %} | {% if c.expressive %}있음{% else %}—{% endif %} |
{% endfor %}

**Accessibility**는 키보드를 이렇게 규정합니다.

| Keys | Action |
|---|---|
{% for k in site.data.button_group.keyboard -%}
| `{{ k.keys }}` | {{ k.action }} |
{% endfor %}

**세그먼트마다 탭 스톱이 하나씩입니다.** 그건 radiogroup이 아닙니다 — 네이티브
radio 그룹은 탭 스톱이 하나이고 그 안에서 화살표로 이동합니다. M3가 서술한 것은
toggle button입니다.

세 모드가 한 요소로 떨어지지 않습니다.

| 모드 | 네이티브 | ARIA | M3 키보드 표와 |
|---|---|---|---|
| multi-select | `input[type=checkbox]` | `button[aria-pressed]` | 일치 |
| single-select + required | `input[type=radio]` | `radio` in `radiogroup` | **불일치** |
| single-select, 해제 가능 | **없음** | `button[aria-pressed]` | 일치 |

세 번째에 주목할 값어치가 있습니다. **네이티브 radio는 사용자가 해제할 수
없습니다** — 다른 것을 고를 수만 있습니다. "single-select이되 required가 아닌"
모드는 HTML에 대응 요소가 없고 반드시 `aria-pressed`입니다.

**이 사이트는 M3 접근성 쪽을 따릅니다.** 기본이 `button[aria-pressed]`이고,
`radio`/`checkbox`는 네이티브 의미가 더 나은 경우의 선택지입니다. 대가는 적어
둡니다 — 스크린 리더가 radiogroup에서는 "3개 중 1개"를 알려주고 toggle button
집합에서는 알려주지 않습니다. 실제 리더로 확인한 것이 아니라 ARIA 규격에서 나오는
추론입니다.

## Measurements

inner padding이 이 컴포넌트에서 유일하게 **그룹이 소유해야만 하는 값**입니다.
버튼 크기에 따라 달라지는데, CSS에서 컨테이너가 자식을 읽는 방향은 없습니다.
그래서 그룹이 size를 선언하고 세그먼트가 상속합니다.

{% assign sizes = site.data.button_group.sizes -%}

| | {% for s in sizes %}{{ s.label }} | {% endfor %}
|---|{% for s in sizes %}---|{% endfor %}
| Container height | {% for s in sizes %}{{ s.height }}dp | {% endfor %}
| Standard between space | {% for s in sizes %}{{ s.standard_between }}dp | {% endfor %}
| Connected between space | {% for s in sizes %}{{ s.connected_between }}dp | {% endfor %}
| Connected inner corner | {% for s in sizes %}{{ s.connected_inner_corner }}dp | {% endfor %}
| Connected pressed inner | {% for s in sizes %}{{ s.connected_pressed_inner_corner }}dp | {% endfor %}
| Connected selected inner | {% for s in sizes %}{{ site.data.button_group.connected_selected_inner_corner }} | {% endfor %}
| 최소 폭 | {% for s in sizes %}{% if s.min_width %}{{ s.min_width }}dp{% else %}—{% endif %} | {% endfor %}

Standard의 spring은 다섯 크기가 전부 damping {{ site.data.button_group.meta.spring_damping }},
stiffness {{ site.data.button_group.meta.spring_stiffness }}이고, 눌림 폭 배수는
{{ site.data.button_group.meta.pressed_width_multiplier | times: 100 }}%입니다. Button과 같은 값이고
같은 문제를 가집니다 — M3가 spring을 주고 웹 커브는 주지 않아서, 어댑터는 발행된
`fast-spatial` 변환을 씁니다.

**XS의 standard between space 18dp는 spacing scale에 없습니다.** 스케일은 14, 16, 20으로
갑니다. 컴포넌트 측정값이고, XS·S가 32dp·40dp 컨테이너에서 48dp 타깃을 지키도록
일부러 넉넉하게 잡힌 값입니다.

{% assign d = site.data.button_group.discrepancies[0] -%}
그리고 **두 문서가 한 값에서 어긋납니다.** measurements 산문은 connected XS 안쪽
모서리를 {{ d.prose }}dp라고 하고, 토큰 표는 {{ d.tokens }}dp라고 합니다. 토큰 표가 더
잘게 나뉘어 있어서 — rest·pressed·selected를 따로 발행합니다 — 이 사이트는 토큰 표를
따릅니다. 산문의 {{ d.prose }}dp는 토큰 표의 pressed 값과 같습니다.

Connected는 다섯 크기가 전부 2dp입니다. 파생값이 아니라 **일관성 규칙**입니다 —
"For all connected button groups, use 2dp padding. This provides visual
consistency at scale."

XS와 S의 큰 standard 여백은 장식이 아니라 **48dp 타깃을 만들기 위한 것**이고,
M3는 이 두 크기에서 여백을 줄이지 말라고 명시합니다.

## Lab specimen matrix

Lab의 Button group 모듈에 있는 모든 표본 유형을 아래에 보존합니다. 이 페이지는
현재 제품이 아닌 계약을 보여 주므로, 표본은 `wp-block-axismundi-button-group`이라는
정적 어댑터를 사용합니다. `core/buttons`가 이 선택 상태를 이미 지원한다고 암시하지
않습니다.

### Connected, multi-select toolbar

Lab Pattern B입니다. 각 버튼이 독립 토글이므로 radio가 아니라
`button[aria-pressed]`를 씁니다.

<div class="sg-button-group-grid">
  <div class="sg-button-group-specimen">
    <div class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" data-selection="multiple" role="toolbar" aria-label="Text formatting">
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true">Bold</button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">Italic</button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">Underline</button>
    </div>
    <p class="sg-button-group-caption">Multiple selection. Any number of formatting toggles may be on.</p>
  </div>
  <div class="sg-button-group-specimen">
    <div class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" data-selection="multiple" role="toolbar" aria-label="Paragraph alignment">
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-label="Align left" aria-pressed="true"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">format_align_left</span></button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-label="Align center" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">format_align_center</span></button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-label="Align right" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">format_align_right</span></button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-label="Justify" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">format_align_justify</span></button>
    </div>
    <p class="sg-button-group-caption">Four icon-only segments. Every icon-only button has an accessible name.</p>
  </div>
</div>

### Segment count and content

The Lab matrix checks 2, 3, 4, and 5 segments, then label-only, leading icon +
label, and icon-only content. These are separate axes: a segment count is
not a content type.

<div class="sg-button-group-grid">
  <div class="sg-button-group-specimen">
    <fieldset class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" aria-label="Billing cycle">
      <legend class="screen-reader-text">Billing cycle</legend>
      <input class="wp-block-axismundi-button-group__input" type="radio" name="billing-cycle" id="billing-monthly" checked />
      <label class="wp-block-axismundi-button-group__item wp-element-button" for="billing-monthly">Monthly</label>
      <input class="wp-block-axismundi-button-group__input" type="radio" name="billing-cycle" id="billing-yearly" />
      <label class="wp-block-axismundi-button-group__item wp-element-button" for="billing-yearly">Yearly</label>
    </fieldset>
    <p class="sg-button-group-caption">2 segments, label-only.</p>
  </div>
  <div class="sg-button-group-specimen">
    <div class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" data-selection="single" data-required="true" role="group" aria-label="View controls">
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">view_list</span>List</button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">grid_view</span>Grid</button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">map</span>Map</button>
    </div>
    <p class="sg-button-group-caption">3 segments, leading icon + label.</p>
  </div>
  <div class="sg-button-group-specimen">
    <div class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" data-selection="single" data-required="true" role="group" aria-label="Editor mode">
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-label="Edit mode" aria-pressed="true"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">edit</span></button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-label="Preview mode" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">visibility</span></button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-label="Publish mode" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">rocket_launch</span></button>
    </div>
    <p class="sg-button-group-caption">3 segments, icon-only.</p>
  </div>
  <div class="sg-button-group-specimen">
    <div class="sg-button-group-mobile-shell">
      <fieldset class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" aria-label="Priority">
        <legend class="screen-reader-text">Priority</legend>
        <input class="wp-block-axismundi-button-group__input" type="radio" name="priority" id="priority-p0" />
        <label class="wp-block-axismundi-button-group__item wp-element-button" for="priority-p0">P0</label>
        <input class="wp-block-axismundi-button-group__input" type="radio" name="priority" id="priority-p1" checked />
        <label class="wp-block-axismundi-button-group__item wp-element-button" for="priority-p1">P1</label>
        <input class="wp-block-axismundi-button-group__input" type="radio" name="priority" id="priority-p2" />
        <label class="wp-block-axismundi-button-group__item wp-element-button" for="priority-p2">P2</label>
        <input class="wp-block-axismundi-button-group__input" type="radio" name="priority" id="priority-p3" />
        <label class="wp-block-axismundi-button-group__item wp-element-button" for="priority-p3">P3</label>
        <input class="wp-block-axismundi-button-group__input" type="radio" name="priority" id="priority-p4" />
        <label class="wp-block-axismundi-button-group__item wp-element-button" for="priority-p4">P4</label>
      </fieldset>
    </div>
    <p class="sg-button-group-caption">5 segments in the Lab's 390px mobile QA shell.</p>
  </div>
</div>

### Size matrix

The Lab originally had partial size hooks. This style guide uses the current
five-size Button contract, so each row below changes height, label treatment,
gap, and connected inner corners together.

<div class="sg-button-group-stack">
  <div class="sg-button-group-specimen"><div class="wp-block-axismundi-button-group" data-variant="connected" data-size="xsmall" data-selection="single" data-required="true" role="group" aria-label="Extra small"><button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true">XS A</button><button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">XS B</button></div><p class="sg-button-group-caption">Xsmall, 32dp container and 48dp minimum segment width.</p></div>
  <div class="sg-button-group-specimen"><div class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" data-selection="single" data-required="true" role="group" aria-label="Small"><button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true">S A</button><button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">S B</button></div><p class="sg-button-group-caption">Small, 40dp container and 48dp minimum segment width.</p></div>
  <div class="sg-button-group-specimen"><div class="wp-block-axismundi-button-group" data-variant="connected" data-size="medium" data-selection="single" data-required="true" role="group" aria-label="Medium"><button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true">M A</button><button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">M B</button></div><p class="sg-button-group-caption">Medium, 56dp container.</p></div>
  <div class="sg-button-group-specimen"><div class="wp-block-axismundi-button-group" data-variant="connected" data-size="large" data-selection="single" data-required="true" role="group" aria-label="Large"><button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true">L A</button><button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">L B</button></div><p class="sg-button-group-caption">Large, 96dp container and 16dp resting inner corner.</p></div>
  <div class="sg-button-group-specimen"><div class="wp-block-axismundi-button-group" data-variant="connected" data-size="xlarge" data-selection="single" data-required="true" role="group" aria-label="Extra large"><button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true">XL A</button><button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">XL B</button></div><p class="sg-button-group-caption">Extra large, 136dp container and 20dp resting inner corner.</p></div>
</div>

### Disabled and bounded ripple

Lab separates native disabled inputs, native disabled buttons, and plugin-managed
`aria-disabled`. The first two are demonstrated here. `aria-disabled` stays
focusable in real products, so the owner must suppress activation and explain
why it is unavailable; this static adapter suppresses it only for safety.

<div class="sg-button-group-grid">
  <div class="sg-button-group-specimen">
    <fieldset class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" aria-label="Native disabled radio">
      <legend class="screen-reader-text">Native disabled radio</legend>
      <input class="wp-block-axismundi-button-group__input" type="radio" name="disabled-radio" id="disabled-radio-open" checked />
      <label class="wp-block-axismundi-button-group__item wp-element-button" for="disabled-radio-open">Open</label>
      <input class="wp-block-axismundi-button-group__input" type="radio" name="disabled-radio" id="disabled-radio-locked" disabled />
      <label class="wp-block-axismundi-button-group__item wp-element-button" for="disabled-radio-locked">Locked</label>
      <input class="wp-block-axismundi-button-group__input" type="radio" name="disabled-radio" id="disabled-radio-later" />
      <label class="wp-block-axismundi-button-group__item wp-element-button" for="disabled-radio-later">Later</label>
    </fieldset>
    <p class="sg-button-group-caption">Disabled is native to the input.</p>
  </div>
  <div class="sg-button-group-specimen">
    <div class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" data-selection="multiple" role="toolbar" aria-label="Disabled buttons">
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="true">Ready</button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false" disabled>Disabled</button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">Next</button>
    </div>
    <p class="sg-button-group-caption">Disabled is native to the button.</p>
  </div>
  <div class="sg-button-group-specimen">
    <div class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" data-selection="multiple" role="toolbar" aria-label="Managed disabled buttons">
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false">Free</button>
      <button type="button" class="wp-block-axismundi-button-group__item wp-element-button" aria-pressed="false" aria-disabled="true">Pro</button>
    </div>
    <p class="sg-button-group-caption">`aria-disabled` is owned by plugin behavior, not CSS alone.</p>
  </div>
</div>

Lab's ripple result also stands: attach bounded ripple to each visible segment,
never the group container. This site does not load a product ripple runtime, so
it intentionally does not counterfeit that effect here. The static examples
verify geometry, semantics, and state; [the Lab module](https://github.com/Jiwoon-Kim/axismundi/tree/main/products/reference-implementations/axismundi-lab/modules/button-group) retains ripple evidence.

### WordPress approximation

This is the current `core/buttons` boundary: it is a row of independent
actions, not a connected selection control. It can model the action-group
example above but cannot emit radio inputs, persist `aria-pressed`, or own the
selection model without a block or plugin.

실측입니다. 760px 부모 안에서 **standard는 내용을 감싸고, connected는 760px를 전부
채웁니다.** 안쪽 여백은 12dp와 2dp입니다.

**폭 변화는 구현하지 않았습니다.** M3의 standard 확장은 그룹 총폭을 유지한 채
재분배하는 것 — 선택된 버튼이 15% 커지고 옆 버튼이 그만큼 줄어듭니다. 내용을 감싸는
컨테이너에서 `flex-grow`로는 표현되지 않습니다. 실제로 걸어보니 남는 공간을 전부
가져가 **선택 세그먼트가 이웃의 11.3배**가 됐습니다. 제대로 하려면 쉬는 상태의 폭을
먼저 재야 하고, 그건 스타일시트가 아니라 스크립트입니다.

## Figma 프로퍼티를 그대로 블록 속성으로 옮길 수 없습니다

Button의 프로퍼티 전체를 보면 셋이 서로 다른 성질입니다.

| Figma 프로퍼티 | 블록에서 | 왜 |
|---|---|---|
| `Type` · `Size` · `Icon` · `Show icon` | attribute | 문서가 저장하는 선택 |
| `Label text` | content | 저장하지만 attribute가 아니라 편집 가능한 내용 |
| `State: Hovered / Focused / Pressed` | **없음** | 브라우저가 만드는 것. `:hover`·`:focus-visible`·`:active` |
| `Show focus indicator` | **없음** | 포커스 링을 그릴지는 저작 결정이 아님 |
| `State: Disabled` | attribute | 이 하나만 저장됨 |

**정적 파일은 모든 상태를 그려 두어야 하니 State가 프로퍼티입니다.** 문서는 한 상태만
저장하고 나머지는 런타임이 만듭니다. `Show focus indicator`가 특히 분명합니다 — 그건
Figma가 링을 보여주기 위한 스위치이지 저장할 값이 아닙니다.

Disabled만 양쪽에 걸칩니다. 비활성 버튼은 **저작되는 것**이고, hover는 아닙니다.

## `core/buttons`가 이미 standard의 절반입니다

앞 절의 액션 그룹은 **저작 모델이 `core/buttons`와 같습니다.** 독립 버튼이 각자
색과 크기를 가지고 나란히 서는 것 — 그게 `core/buttons`의 정의입니다. 이 페이지가
처음에 standard 전체를 선택 컨트롤로 단정했던 것은 Lab의 radio fieldset 구현 하나를
보고 내린 판단이었고, 좁았습니다.

지금 `core/buttons`에 없는 것을 세어보면 이렇습니다.

| standard가 요구하는 것 | `core/buttons` | 무엇이 필요한가 |
|---|---|---|
| 독립 버튼, 각자 색 | 있음 | — |
| 강조를 위한 크기 혼합 | 없음 | `core/button`의 size attribute |
| 아이콘 버튼 동거 | 없음 | `allowedBlocks`가 `core/button`뿐 — 코어 변경 |
| 크기별 안쪽 여백 | 없음 | 컨테이너의 size 축 |
| 활성화 시 폭 재분배 | 없음 | 스크립트 |
| 토글 구성원 | 없음 | `aria-pressed`를 저장할 자식 |

**앞의 다섯은 `core/buttons`에 더하는 것이고, 마지막 하나만 다른 자식 블록을
요구합니다.** 그리고 그 마지막은 standard에서 선택 사항입니다.

그래서 경계가 여기서 갈립니다.

```
선택이 없는 standard   core/buttons + variation + 스크립트
선택이 있는 standard   axismundi/button-group + item
connected              항상 선택 컨트롤 — 도메인 블록이거나 위와 같은 쌍
```

`core/buttons` 쪽 세부는
[Button and Buttons]({{ '/wordpress/button-and-buttons/' | relative_url }})가 다룹니다.

## 이미 두 개가 출하 중입니다

가상의 컴포넌트가 아닙니다. connected 기하를 쓰는 컨트롤이 이 저장소에 둘 있고,
**의미가 서로 다릅니다.**

```
axismundi/theme-switcher     role="group" + button[aria-pressed]    M3 정합
axismundi-activities         <nav> + a[aria-current="page"]         기하만 차용
   feed density switch
```

**둘의 지위가 다릅니다.** Theme Switcher는 토글 세그먼트를 가진 진짜 connected
group입니다. 밀도 전환은 connected의 기하를 쓰지만 세그먼트가 링크라, M3 기준으로는
connected를 쓰지 말아야 할 경우입니다 — 위 5번이 그 이야기입니다.

그래도 어댑터는 두 속성을 모두 읽습니다. 링크의 현재 상태를 `aria-current`로 쓰는
것 자체는 옳고, 그 마크업이 이미 출하 중이기 때문입니다.

```css
.wp-block-axismundi-button-group__item[aria-pressed="true"],
.wp-block-axismundi-button-group__item[aria-current="page"] { … }
```

**그룹은 기하와 배치를 소유하고, 그것을 쓰는 컨트롤이 선택의 의미를 소유합니다.**

## 블록 계약

아직 어느 쪽도 블록으로 등록하지 않았습니다. 하지만 Figma의 자식 모델은 이미
두 저장 형태를 가릅니다.

```
Standard button group                     Connected button group
└─ InnerBlocks                            attributes
   ├─ core/button                             type · size · showThirdSegment
   └─ (future) core/icon-button               segments[0..2]
                                               └─ selected · state · icon · iconSelected
                                                  showLabelText · labelText
```

**Standard만 nested입니다.** 자식이 독립 Button/Icon button 인스턴스이므로 그
블록을 그대로 삽입하고 편집해야 합니다. Connected의 `Segment 1 / Segment 2 / End
segment`는 재사용 가능한 Button 블록이 아니라 부모 컴포넌트의 고정 슬롯입니다.
`show 3rd segment`도 InnerBlocks inserter가 아니라 부모의 구조 attribute입니다.

따라서 두 variant를 하나의 `variant` attribute로 전환하면 안 됩니다. Standard의
InnerBlocks와 Connected의 제한된 `segments` 데이터는 동형이 아니며, 전환할 때 어느
한쪽의 내용을 버리거나 추측해서 변환하게 됩니다.

| 코어 | 무엇을 증명하나 |
|---|---|
| `core/accordion.autoclose` | Standard처럼 부모가 선택 정책을 가질 수 있음 |
| `core/tabs.activeTabIndex` | 부모가 활성 상태를 소유할 수 있음 |
| `core/social-links` | InnerBlocks 자식에 부모 context를 내릴 수 있음 |

이 코어 선례는 Standard의 nested 편집을 뒷받침합니다. Connected는 그와 다른
고정 구성입니다. Segment는 type·size·color를 렌더하지 않으므로, 색 혼합을 막기 위해
InspectorControls를 숨기는 우회도 필요 없습니다.

축은 이렇게 나뉩니다.

| 축 | 어디 | 왜 |
|---|---|---|
| Standard child: type · size · color · width · state | InnerBlock | Button/Icon button이 소유 |
| Connected: type · size · showThirdSegment | 부모 attribute | 고정 Segment 구조와 기하 |
| Connected Segment: selected · state · icon · iconSelected · showLabelText · labelText | 부모의 `segments` attribute | 독립 Button이 아닌 구성 부품 |

Connected의 선택 element는 별도 접근성 계약입니다. radio를 선택하면
`<fieldset>`·`<legend>`·공유 `name`·화살표 키 규칙을 함께 렌더해야 하므로 단순한
태그 선택보다 큽니다. 실제 사용처가 생길 때 `button`/radio/checkbox 모델을 정합니다.

## 왜 아직 만들지 않는가

**두 소비자가 이미 각자의 집을 가지고 있기 때문입니다.** Theme Switcher는 색
구성표 상태를 소유하고, 밀도 전환은 URL을 소유합니다. 둘 다 도메인 블록이지
범용 그룹이 아닙니다.

토글도 아니고 내비게이션도 아닌 세 번째 선택 UI가 나타날 때, 그때가 이 블록을
만들 시점입니다. 그 전까지 이 페이지는 **계약 초안**이고, 제품에 급히 등록하지
않습니다.
