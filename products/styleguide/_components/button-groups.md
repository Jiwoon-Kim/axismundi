---
title: Button groups
description: 색이 없는 컨테이너, 선택을 소유하는 컨트롤, 그리고 스펙 두 문서가 서로 다른 말을 하는 지점
kind: material
order: 30
lang: ko
---

Button group은 **색 속성이 없습니다.** M3가 그렇게 씁니다 — "Button groups have
no color properties." 색은 안에 든 버튼의 것이고, 그룹이 색에 대해 가진 규칙은
금지 하나뿐입니다: **connected에서 색을 섞지 말 것.**

그래서 이 페이지에는 색 축이 없습니다. 축은 셋입니다 — variant, size, selection.

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

**모서리가 움직이는 것이 shape morph입니다.** 쉬는 세그먼트는 안쪽 8px, 선택된 것은
50%, 누르는 동안은 4px입니다. 세 값이 전부 M3가 따로 발행한 것입니다.

### 5. Connected, 내비게이션

밀도 전환이 실제로 쓰는 모양입니다. 세그먼트가 링크이고 선택 표시가
`aria-current="page"`입니다. **클릭해도 여기서는 바뀌지 않습니다** — 링크의 현재
상태는 서버의 답이지 클릭 핸들러의 것이 아니라서, 정적으로 둡니다.

<div class="sg-demo">
  <nav class="wp-block-axismundi-button-group" data-variant="connected" data-size="small" aria-label="Entry density">
    <a class="wp-block-axismundi-button-group__item wp-element-button" href="#1">Card</a>
    <a class="wp-block-axismundi-button-group__item wp-element-button" href="#2" aria-current="page">List</a>
    <a class="wp-block-axismundi-button-group__item wp-element-button" href="#3">Compact</a>
  </nav>
</div>

실측입니다. 760px 부모 안에서 **standard는 내용을 감싸고, connected는 760px를 전부
채웁니다.** 안쪽 여백은 12dp와 2dp입니다.

**폭 변화는 구현하지 않았습니다.** M3의 standard 확장은 그룹 총폭을 유지한 채
재분배하는 것 — 선택된 버튼이 15% 커지고 옆 버튼이 그만큼 줄어듭니다. 내용을 감싸는
컨테이너에서 `flex-grow`로는 표현되지 않습니다. 실제로 걸어보니 남는 공간을 전부
가져가 **선택 세그먼트가 이웃의 11.3배**가 됐습니다. 제대로 하려면 쉬는 상태의 폭을
먼저 재야 하고, 그건 스타일시트가 아니라 스크립트입니다.

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

## 이미 두 개가 출하 중입니다

가상의 컴포넌트가 아닙니다. connected 기하를 쓰는 컨트롤이 이 저장소에 둘 있고,
**의미가 서로 다릅니다.**

```
axismundi/theme-switcher     role="group" + button[aria-pressed]    클라이언트 토글
axismundi-activities         <nav> + a[aria-current="page"]         서버 내비게이션
   feed density switch
```

두 번째가 특히 배울 점입니다. 밀도 전환은 **URL 파라미터**라 세그먼트가 링크이고,
선택 표시가 `aria-pressed`가 아니라 `aria-current="page"`입니다. 그게 맞습니다 —
누르는 토글이 아니라 현재 보고 있는 뷰니까요. M3도 connected의 용도로 "select
options, **switch views**, or sort elements"를 듭니다.

그래서 이 어댑터는 두 속성을 모두 읽습니다.

```css
.wp-block-axismundi-button-group__item[aria-pressed="true"],
.wp-block-axismundi-button-group__item[aria-current="page"] { … }
```

**그룹은 기하와 배치를 소유하고, 그것을 쓰는 컨트롤이 선택의 의미를 소유합니다.**

## 블록 계약

`axismundi/button-group` 블록은 **아직 없습니다.** 아래는 만든다면 가져야 할
형태이고, 코어 선례에서 끌어왔습니다.

```
axismundi/button-group
└─ axismundi/button-group-item × N
```

**두 variant 모두 nested입니다.** connected를 flat `items[]`로 하자는 안을
검토했지만, 코어가 이 모양을 어떻게 푸는지 읽고 접었습니다.

| 코어 | 무엇을 증명하나 |
|---|---|
| `core/accordion.autoclose` | 단일/다중 선택을 **부모 boolean**으로, 자식은 nested |
| `core/tabs.activeTabIndex` | 선택 상태를 부모가 소유, 자식은 nested |
| `core/tab-list.tabs` | `source: "query"` — 마크업에서 **파생된 거울**이지 저장된 배열이 아님 |
| `core/social-links` | `providesContext` → `core/social-link`의 `usesContext` |

**코어에 flat `items[]` 선례가 없습니다.** 배열 뷰가 필요하면 nested 자식에서
파생시킵니다. 그리고 "connected에서 색을 섞지 못하게" 하는 데에 평탄화가 필요하지도
않습니다 — 자식이 **색 컨트롤을 렌더하지 않으면** 됩니다. 부모 context가 connected일
때 InspectorControls를 숨기는 쪽이고, 그러면 variant를 바꿔도 자식이 사라지지
않습니다. 저장된 값은 남고 존중되지 않을 뿐이라 되돌릴 수 있습니다.

축은 이렇게 나뉩니다.

| 축 | 어디 | 왜 |
|---|---|---|
| `variant` | 부모 attribute | 기하와 인접 상호작용. 자식은 알 필요 없음 |
| `selection` · `required` | 부모 attribute | `core/accordion.autoclose`와 같은 자리 |
| `size` · `shape` | 부모 → context | 세그먼트 표면이 소비. 그룹만 안쪽 여백을 계산할 수 있음 |
| `element` | 부모 attribute | radio는 공유 `name`이 필요하고, 그 값은 그룹만 앎 |
| label · icon · value | 자식 attribute | 세그먼트가 소유 |

`element`가 `radio | checkbox | button`인 것은 `core/button`의
`tagName: a | button` 선례를 따릅니다. 다만 **기본은 `button`**이고, radio는
`<fieldset>`·`<legend>`·공유 `name`·화살표 키 규칙을 한 묶음으로 데려오므로 단순한
태그 선택보다 큰 계약입니다. 실제 사용처가 생길 때 엽니다.

## 왜 아직 만들지 않는가

**두 소비자가 이미 각자의 집을 가지고 있기 때문입니다.** Theme Switcher는 색
구성표 상태를 소유하고, 밀도 전환은 URL을 소유합니다. 둘 다 도메인 블록이지
범용 그룹이 아닙니다.

토글도 아니고 내비게이션도 아닌 세 번째 선택 UI가 나타날 때, 그때가 이 블록을
만들 시점입니다. 그 전까지 이 페이지는 **계약 초안**이고, 제품에 급히 등록하지
않습니다.

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
