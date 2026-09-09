---
title: Buttons
description: 다섯 색 스타일과 다섯 크기, 그리고 그 둘이 서로를 구속하지 않는 이유
kind: material
order: 20
lang: ko
---

M3의 button은 **두 개의 독립 축**을 가집니다. 색 스타일이 크기를 정하지 않고,
크기가 색 스타일을 정하지 않습니다. 이 페이지의 표가 하나의 행렬이 아니라 두 개의
목록인 이유입니다. WordPress의 단일 `is-style-*` variation 축은 색에만 남기고,
크기와 모양은 독립 attribute가 렌더한 `data-size`·`data-shape`가 맡습니다.
`core/button`에는 아직 이 attribute들이 없으므로, 여기의 크기 표본은 그 editor contract를 앞서 보여주는
정적 어댑터입니다.

## Interactive demo

아래는 별도 구현을 흉내 내는 미리보기가 아닙니다. WordPress가 저장하는
`.wp-block-button > .wp-block-button__link` 마크업 하나에, 이 페이지가 문서화하는
style variation과 정적 adapter attribute를 직접 적용합니다. 값은 inspector에서 바꾸고,
결과는 같은 DOM에서 바로 확인합니다.

<section class="sg-button-playground" data-button-playground aria-label="Button interactive demo">
  <div class="sg-button-playground__stage" aria-label="Result">
    <p class="sg-button-playground__eyebrow">Result</p>
    <div class="wp-block-buttons">
      <div class="wp-block-button" data-playground-button>
        <button type="button" class="wp-block-button__link wp-element-button">
          <span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true" draggable="false" data-playground-icon>stars</span><span data-playground-label>Label</span>
        </button>
      </div>
    </div>
    <pre class="sg-button-playground__markup" data-playground-markup aria-label="Proposed block markup"></pre>
  </div>
  <form class="sg-button-playground__controls" aria-label="Button controls">
    <p class="sg-button-playground__eyebrow">Controls</p>
    <label class="sg-button-playground__control">Label text
      <input type="text" value="Label" data-button-control="label" />
    </label>
    <label class="sg-button-playground__control">Style
      <select data-button-control="style">
        <option value="filled">Filled</option>
        <option value="tonal">Tonal</option>
        <option value="outlined">Outlined</option>
        <option value="elevated">Elevated</option>
        <option value="text">Text</option>
      </select>
    </label>
    <label class="sg-button-playground__control">Size
      <select data-button-control="size">
        <option value="xsmall">Extra small</option>
        <option value="small" selected>Small</option>
        <option value="medium">Medium</option>
        <option value="large">Large</option>
        <option value="xlarge">Extra large</option>
      </select>
    </label>
    <label class="sg-button-playground__control">Shape
      <select data-button-control="shape">
        <option value="round">Round</option>
        <option value="square">Square</option>
      </select>
    </label>
    <label class="sg-button-playground__control">Icon
      <input type="text" list="button-icon-options" value="stars" data-button-control="icon" />
    </label>
    <label class="sg-button-playground__check">
      <input type="checkbox" checked data-button-control="showIcon" /> Show icon
    </label>
    <label class="sg-button-playground__check">
      <input type="checkbox" data-button-control="disabled" /> Disabled
    </label>
    <label class="sg-button-playground__check">
      <input type="checkbox" data-button-control="softDisabled" /> Soft disabled
    </label>
  </form>
</section>
<datalist id="button-icon-options">
  <option value="stars">stars</option>
  <option value="add">add</option>
  <option value="edit">edit</option>
  <option value="search">search</option>
  <option value="arrow_forward">arrow_forward</option>
</datalist>

이 페이지의 모든 버튼은 실제로 동작합니다. 기본 마크업은 블록 에디터가 쓰는 것과 같은
`.wp-block-button > .wp-block-button__link`이고, CSS는 테마의 계약을 정적으로
재진술한 것입니다. 아래 icon 표본만은 현재 `core/button`에 없는 slot을 문서화한
Material reference입니다.

## Anatomy

```
Container      필수. outlined·text에서는 쉬는 상태에 보이지 않음
Label text     필수
Icon           선택
```

Icon은 label의 의미를 반복하지 않는 장식이며 `aria-hidden`입니다. **label은 언제나
남습니다** — 아이콘만 남는 것은 button이 아니라 icon button입니다.

<div class="sg-demo">
  <div class="wp-block-buttons">
    <div class="wp-block-button"><button type="button" class="wp-block-button__link wp-element-button"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true" draggable="false">search</span>Search</button></div>
    <div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link wp-element-button"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true" draggable="false">edit</span>Edit playlist</button></div>
  </div>
</div>

### 폭은 label이 정합니다

M3가 Do/Don't로 명시한 것입니다.

```
Do     버튼 폭이 label에 맞춰 늘어난다
Don't  label보다 좁은 고정 폭을 주지 않는다
```

그래서 이 어댑터는 링크에 폭을 선언하지 않습니다. 하지만 그것만으로는 부족했습니다 —
**측정해 보니 컨테이너가 110px일 때 버튼이 134px에서 110px로 눌렸습니다.** `core/buttons`가
flex이고 자식의 `flex-shrink`가 1이라, 고정 폭을 주지 않아도 눌립니다. 눌린 label은
줄바꿈되고, 컨테이너 높이는 크기마다 고정이므로 **둘째 줄이 잘려서 보이지도 않습니다.**

`white-space: nowrap` 한 줄이 이걸 막습니다. 줄바꿈이 없으면 min-content가 label 전체가
되고, flex는 min-content 아래로 자식을 줄이지 않습니다. 폭을 금지해서가 아니라
**최소 폭을 label로 만들어서** 규칙이 성립합니다.

### 아이콘은 leading side입니다

M3 가이드라인이 아이콘에 대해 말하는 것은 다음이 전부입니다.

```
Do     label 앞, leading side에 둔다
Do     의미가 분명한 아이콘을 쓴다
Don't  아이콘과 텍스트를 세로 가운데로 쌓지 않는다
Don't  한 버튼에 아이콘 두 개를 쓰지 않는다
```

"LTR에서는 label 왼쪽, RTL에서는 오른쪽"이라고 쓰여 있지만 **이건 두 규칙이 아니라
하나를 물리 방향으로 두 번 쓴 것**입니다. leading은 논리 개념이라 구현할 것이 없습니다 —
slot은 문서 순서대로 놓이는 flex 자식이고, row는 쓰기 방향을 따라 저절로 뒤집힙니다.

**trailing icon은 M3가 서술하지 않습니다.** 없다고 쓰여 있는 게 아니라 다루지 않습니다.
그래서 이 페이지는 leading만 보여주고, trailing이 필요해지면 그건 Material의 결정이
아니라 Axismundi의 결정으로 기록되어야 합니다. 아이콘이 하나뿐인 것은 명시된
Don't이므로 slot도 하나뿐입니다.

### Icon은 값이 아니라 참조입니다

블록이 저장하는 것은 glyph가 아니라 **참조**이고, 그 참조를 푸는 방법이 둘입니다.

```
icon: "core/search"     Icon Registry 참조
icon: "contrast"        이름을 직접 입력
```

첫째는 WordPress 7.1이 공개한 icon registry입니다 — `wp_register_icon_collection()`과
`wp_register_icon()`이 실제로 존재하고, 이 저장소에도 이미 쓰는 곳이 있습니다
([`axismundi-contacts/includes/icons.php`]({{ site.repository_url }}/blob/main/products/wordpress/plugins/axismundi-contacts/includes/icons.php)가
자기 컬렉션을 등록하고 `function_exists` 가드로 구버전을 폴백합니다).

둘째는 이미 우리 제품이 하고 있는 방식입니다. `axismundi/dialog`의 `triggerIcon`은
`TextControl`에 Material Symbols 이름을 그대로 받고, 서버가 그것을 ligature로
렌더합니다. **registry에 없는 아이콘을 위한 탈출구**이고, 이게 필요한 이유는
구체적입니다 — 코어 컬렉션 88개에 **light/dark/contrast 계열이 하나도 없습니다.**
색 구성표 스위처조차 registry만으로는 그릴 수 없습니다.

두 경로가 만나는 지점이 slot의 클래스입니다.

```html
<span class="wp-block-button__icon" aria-hidden="true"> … </span>
```

**클래스가 계약이고, 안에 무엇이 들어가는지는 계약이 아닙니다.** registry 참조는
`<svg>`로, 입력한 이름은 ligature로 도착하며, 크기 규칙은 상자만 정하고 내용물이
그 상자를 채우게 둡니다. 그래서 소스마다 별도 CSS가 필요 없습니다.

위 표본은 WordPress 런타임이 없는 정적 사이트이므로 둘 다 ligature로 그려집니다.

**`core/button`에는 아직 이 slot이 없습니다.** registry를 소비하는 코어 블록은
`core/icon` 하나뿐이라, 이 절은 바인딩이 아니라 **그 slot이 생겼을 때의 저장·렌더
계약을 미리 적어 둔 것**입니다.

## 두 변형

**Default button**과 **Toggle button**입니다. 같은 컴포넌트의 두 모드가 아니라
**색이 서로 다른 두 변형**입니다 — 아래 색 표의 열이 셋인 이유입니다.

| Variant | M3 | M3 Expressive |
|---|---|---|
{% for v in site.data.button.variants -%}
| {{ v.title }} | {% if v.m3 %}있음{% else %}—{% endif %} | {% if v.expressive %}있음{% else %}—{% endif %} |
{% endfor %}
Toggle은 **Expressive에만 있습니다.** Size·Shape의 확장과 같은 자리입니다.

상태를 나르는 것은 클래스가 아니라 **`aria-pressed`**입니다. 토글은 선택 상태를 가진
컨트롤이고, 그 상태는 보조기술에 닿아야 합니다. 속성이 있으면 비선택, `"true"`면
선택입니다 — [Icon buttons]({{ '/components/icon-buttons/' | relative_url }})와
Connected Segment가 쓰는 계약과 같습니다.

```html
<button class="wp-block-button__link wp-element-button" aria-pressed="false">Bold</button>
<button class="wp-block-button__link wp-element-button" aria-pressed="true">Italic</button>
```

토글에는 **text 스타일이 없습니다.** 선택 상태를 알릴 container가 없으니
선택/비선택을 색으로 구분할 방법이 사라집니다. 어댑터는 그래서 `is-style-text`를
토글 규칙에서 **제외합니다.** 그냥 두면 기본 규칙의 Filled 토글 표를 물려받아,
M3에 없는 토글이 조용히 그려집니다.

선택은 색만으로 알리지 않습니다. **쉬는 shape도 바뀝니다** — round는 각지고, 각진
것은 둥글어집니다. M3는 방향이 아니라 대비로 규정합니다. round의 값은 높이의 절반이라
square에서 선택될 때도 `--ax-button-shape`가 아니라 `calc(height / 2)`를 씁니다.
square가 그 변수를 이미 덮어썼기 때문입니다.

### Icon(selected)는 두 번째 아이콘이 아닙니다

Figma의 Toggle button 프로퍼티에는 아이콘이 둘입니다.

```
Icon            {{ site.data.button.variants[1].icon }}
Icon(selected)  {{ site.data.button.variants[1].icon_selected }}
```

정적 파일은 채워진 아이콘을 보이려면 기호를 바꿔 끼우는 수밖에 없으니 그렇게 적혀
있습니다. **`stars_filled`는 Material Icons의 이름이고 Material Symbols로 넘어오면서
없어졌습니다.** 가변 폰트에는 `FILL` 축이 있으므로 웹은 글리프가 아니라 축을 바꿉니다 —
`icons.css`가 `@property`로 등록해 두어 스냅이 아니라 보간됩니다. 마크업의 아이콘
이름은 하나, 그려지는 상태는 둘입니다.

> In toggle buttons, use the outlined style of an icon for the unselected
> state, and the filled style for the selected state.

축을 **글리프 폰트가 아니라 slot에** 씁니다. geometry 규칙이 그러는 이유와 같습니다 —
7.1 아이콘 레지스트리 참조는 `<svg>`로 오고, `<svg>`에는 FILL 축이 없어 변수를 잘못
읽는 대신 그냥 무시합니다.

Figma가 `Show icon`의 기본값을 **true**로 두는 것도 그래서입니다. 평범한 button에서
아이콘은 선택 사항이지만, 토글에서는 fill이 선택을 알리는 일을 합니다.

<div class="sg-demo sg-demo--stack">
{% assign toggle = site.data.button.variants[1] -%}
{% for c in site.data.button.colors -%}
{% if c.toggle_selected -%}
  <div class="wp-block-buttons">
    <div class="wp-block-button{% unless c.name == 'filled' %} {{ c.wp_style }}{% endunless %}"><button type="button" class="wp-block-button__link wp-element-button" aria-pressed="false"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">{{ toggle.icon }}</span><span>{{ c.title }} unselected</span></button></div>
    <div class="wp-block-button{% unless c.name == 'filled' %} {{ c.wp_style }}{% endunless %}"><button type="button" class="wp-block-button__link wp-element-button" aria-pressed="true"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">{{ toggle.icon }}</span><span>{{ c.title }} selected</span></button></div>
  </div>
{% endif -%}
{% endfor -%}
</div>

두 줄의 아이콘 이름은 `{{ toggle.icon }}` 하나입니다. 선택된 쪽만 채워집니다.

Text가 이 목록에 없는 것이 위 문장의 증거입니다. 표본은 `button.yml`의
`toggle_selected`가 있는 스타일만 그립니다. 어댑터도 같은 이유로 `is-style-text`를
색과 shape **양쪽** 토글 규칙에서 제외합니다. 한쪽만 제외하면 색은 그대로인데 모서리만
바뀌는 반쪽 토글이 그려집니다 — 실제로 그랬습니다.

### Show focus indicator

Figma에 이 프로퍼티가 있는 이유는 정적 파일이 링을 직접 그려야 보여줄 수 있기
때문입니다. 실제 링은 브라우저가 소유하고 `:focus-visible`은 키보드 포커스에만
반응하는데, 페이지를 읽는 사람은 그러고 있지 않습니다. 그래서 표본은 같은 링을
강제합니다 — 이 클래스 뒤에는 저장되는 블록 속성이 **없습니다.**

<div class="sg-demo">
  <div class="wp-block-buttons">
    <div class="wp-block-button is-forced-focus"><button type="button" class="wp-block-button__link wp-element-button">Focused</button></div>
    <div class="wp-block-button is-forced-focus"><button type="button" class="wp-block-button__link wp-element-button" aria-pressed="true"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">{{ toggle.icon }}</span><span>Focused selected</span></button></div>
  </div>
</div>

## Configurations

| Configuration | Option | M3 | M3 Expressive |
|---|---|---|---|
{% for row in site.data.button.availability -%}
| {{ row.configuration }} | {{ row.option }} | {% if row.m3 %}있음{% else %}—{% endif %} | {% if row.expressive %}있음{% else %}—{% endif %} |
{% endfor -%}
| Small 좌우 여백 | {{ site.data.button.meta.small_space_legacy }}dp | 있음 | 권장하지 않음 |
| Small 좌우 여백 | 16dp | — | 있음 |

마지막 두 행이 이 프로젝트가 실제로 결정한 지점입니다. **16dp만 씁니다.**
Expressive가 24dp를 권장하지 않는다고 명시했고, 두 값을 모두 지원하면 어느 쪽도
기본값이 아니게 됩니다.

## Color

색은 **값이 아니라 역할**입니다. 아래 표의 칸은 전부 color role 이름이고, 실제
값은 [Color]({{ '/foundations/color/' | relative_url }})의 매핑을 거칩니다.

| Style | Default | Toggle unselected | Toggle selected |
|---|---|---|---|
{% for c in site.data.button.colors -%}
| **{{ c.title }}** | {% if c.container %}`{{ c.container }}`{% if c.container_is_outline %} (outline){% endif %} / {% endif %}`{{ c.content }}` | {% if c.toggle_unselected %}`{{ c.toggle_unselected.container }}` / `{{ c.toggle_unselected.content }}`{% else %}—{% endif %} | {% if c.toggle_selected %}`{{ c.toggle_selected.container }}` / `{{ c.toggle_selected.content }}`{% else %}—{% endif %} |
{% endfor %}

Outlined 행의 첫 역할은 **container가 아니라 outline**입니다. outlined의 container는
쉬는 상태에 보이지 않고, text는 container 자체가 없습니다.

M3가 붙인 단서가 있습니다 — 이 역할들은 설계적 일관성을 위한 선택이고, **container와
label이 3:1 대비를 지키면 다른 역할도 쓸 수 있습니다.** 예를 들어 tertiary와
on tertiary입니다.

<div class="sg-demo">
  <div class="wp-block-buttons">
    <div class="wp-block-button"><button type="button" class="wp-block-button__link wp-element-button">Filled</button></div>
    <div class="wp-block-button is-style-elevated"><button type="button" class="wp-block-button__link wp-element-button">Elevated</button></div>
    <div class="wp-block-button is-style-tonal"><button type="button" class="wp-block-button__link wp-element-button">Tonal</button></div>
    <div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link wp-element-button">Outlined</button></div>
    <div class="wp-block-button is-style-text"><button type="button" class="wp-block-button__link wp-element-button">Text</button></div>
  </div>
</div>

## States

State layer는 button의 것이 아니라 [Interaction]({{ '/foundations/state/' | relative_url }})의
것입니다. 다섯 스타일이 전부 같은 불투명도를 쓰고, 스타일마다 다른 것은 **무엇 위에
무엇을 섞느냐**뿐입니다.

```
hover    0.08     content role 을 container 위에
focus    0.10     + focus ring (secondary, 3px, offset 2px)
pressed  0.10     + 모서리 morph
disabled 0.38     레이어가 아니라 content 에 걸리는 평평한 불투명도
                  container 는 on-surface 10%
```

Outlined와 text의 container는 쉬는 상태에 보이지 않지만, **불투명도와 state layer는
다른 스타일과 똑같이 동작합니다.** 투명 위에 섞이므로 뒤에 있는 것의 색조로
읽힙니다.

Elevated는 유일하게 쉬는 상태에 elevation을 가집니다 — level 1이고, disabled에서
0입니다.

<div class="sg-demo">
  <div class="wp-block-buttons">
    <div class="wp-block-button"><button type="button" class="wp-block-button__link wp-element-button">Enabled</button></div>
    <div class="wp-block-button"><button type="button" class="wp-block-button__link wp-element-button" disabled>Disabled</button></div>
    <div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link wp-element-button">Enabled</button></div>
    <div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link wp-element-button" disabled>Disabled</button></div>
  </div>
</div>

## Shape morph

눌리면 버튼이 **더 사각형에 가깝게 변합니다.** round와 square는 쉬는 모양이 다르지만
**눌린 모양은 같습니다.**

토글은 여기에 하나를 더합니다 — 선택되면 쉬는 모양 자체가 바뀝니다. round가 선택되면
square가 되고, **원래 square였다면 선택될 때 round가 됩니다.** 방향이 아니라 대비가
신호입니다.

morph 값이 크기마다 다른 것은 [Shape]({{ '/foundations/shape/' | relative_url }})가
말하는 이유 때문입니다. 시작과 끝이 모두 `corner-value`, 즉 유한한 길이여야 합니다.
pill 반지름은 길이가 아니라서 전환의 끝점이 될 수 없습니다.

<div class="sg-demo">
  <div class="wp-block-buttons">
    <div class="wp-block-button is-style-tonal"><button type="button" class="wp-block-button__link wp-element-button">눌러 보세요</button></div>
    <div class="wp-block-button is-style-tonal" data-shape="square"><button type="button" class="wp-block-button__link wp-element-button">Square</button></div>
  </div>
</div>

M3는 크기마다 spring도 함께 발행합니다 — damping {{ site.data.button.meta.spring_damping }},
stiffness {{ site.data.button.meta.spring_stiffness }}로 다섯 크기가 모두 같습니다.
웹으로 옮길 published curve가 없어서, 여기서는
[Motion]({{ '/foundations/motion/' | relative_url }})의 `fast-spatial` 변환을 씁니다.

## Measurements

{% assign sizes = site.data.button.sizes -%}

| | {% for s in sizes %}{{ s.label }} | {% endfor %}
|---|{% for s in sizes %}---|{% endfor %}
| Container height | {% for s in sizes %}{{ s.height }}dp | {% endfor %}
| Leading / trailing | {% for s in sizes %}{{ s.space }}dp | {% endfor %}
| Icon size | {% for s in sizes %}{{ s.icon }}dp | {% endfor %}
| Icon–label space | {% for s in sizes %}{{ s.icon_gap }}dp | {% endfor %}
| Outline width | {% for s in sizes %}{{ s.outline_width }}dp | {% endfor %}
| Label | {% for s in sizes %}{{ s.type.size }}/{{ s.type.line_height }} · {{ s.type.weight }} | {% endfor %}
| Tracking | {% for s in sizes %}{{ s.type.tracking }}pt | {% endfor %}

라벨 서체는 크기마다 **직접 발행된 값**입니다. type scale의 role로 매핑되지 않습니다 —
14/20 500과 16/24 500까지는 label-large·title-medium과 겹쳐 보이지만 24/32 400과
32/40 400은 어느 role과도 맞지 않습니다. 그래서 이 표는 role 이름이 아니라 숫자입니다.

### Corner sizes

| | {% for s in sizes %}{{ s.label }} | {% endfor %}
|---|{% for s in sizes %}---|{% endfor %}
| Round | {% for s in sizes %}Full | {% endfor %}
| Square | {% for s in sizes %}{{ s.shape_square }}dp | {% endfor %}
| Pressed | {% for s in sizes %}{{ s.pressed_morph }}dp | {% endfor %}
| Selected (round 기준) | {% for s in sizes %}{{ s.selected_round }}dp | {% endfor %}

<div class="sg-demo sg-demo--wrap">
  <div class="wp-block-buttons">
{%- for s in sizes %}
    <div class="wp-block-button"{% unless s.name == 'small' %} data-size="{{ s.name }}"{% endunless %}><button type="button" class="wp-block-button__link wp-element-button"><span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true" draggable="false">add</span>{{ s.label }}</button></div>
{%- endfor %}
  </div>
</div>

### Target areas

**XS와 S는 최소 48×48dp의 타깃이 필요합니다.** 컨테이너가 32dp나 40dp이므로
시각적 크기와 타깃 크기가 일치하지 않고, 그 차이를 메우는 것은 컴포넌트의 책임입니다.

## 지금 바인딩된 것

{% assign bound_sizes = sizes | where: "bound", true -%}
{% assign bound_colors = site.data.button.colors | where: "bound", true -%}

```
색 스타일   {{ bound_colors.size }} / {{ site.data.button.colors.size }}   {% for c in bound_colors %}{{ c.name }} {% endfor %}
크기        {{ bound_sizes.size }} / {{ sizes.size }}   {% for s in bound_sizes %}{{ s.name }} {% endfor %}
```

이 페이지가 보여주는 다른 크기들은 **문서용 구현**입니다. 실제 테마는 Small만
발행합니다. 무엇이 왜 아직 없는지는
[WordPress 바인딩]({{ '/wordpress/button-and-buttons/' | relative_url }})에 있습니다.

## 결정 지점

**하나의 button에서는 button이 크기와 스타일을 정합니다.** `core/buttons`는 그런
button을 배치하는 action container입니다. 상태를 공유하는 진짜 M3 Button group에서만
group이 크기·스타일과 선택 상태를 정합니다 — 구성원이 독립된 button이 아니라
**segment**이기 때문입니다.

`core/buttons.is-style-connected`는 Button group 그 자체가 아니라 연결된 외형을 주는
WordPress variation입니다. 이 경계가 CSS와 블록 마크업에서 어떻게 성립하는지는
바인딩 페이지에 있습니다.
