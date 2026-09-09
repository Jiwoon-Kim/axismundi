---
title: Icon buttons
description: 라벨이 없는 버튼, 그리고 그래서 생기는 네 가지 차이
kind: material
order: 40
lang: ko
---

Button과 거의 같은 축을 가지는데, **anatomy에 라벨이 없습니다.**

```
Button       Container · Label text · Icon(선택)
Icon button  Container · Icon
```

그 한 줄에서 나머지가 따라옵니다. 이름이 보이는 자리에 없으니 **보이지 않는 곳에
있어야 하고**, M3가 웹에 툴팁을 요구하는 것도, 아이콘이 "명확한 의미"를 가져야 한다고
못박는 것도 같은 이유입니다.

## Interactive demos

Figma가 컴포넌트를 둘로 나눠 두었고 여기서도 둘입니다. **togglable 쪽에만 `Selected`와
`Icon(selected)`가 있습니다** — 하나로 합치면 기본 아이콘 버튼에 없는 컨트롤을 만드는
셈이 됩니다.

프로퍼티 목록에서 눈에 띄는 것이 하나 더 있습니다. **두 컴포넌트 어디에도 `Color`가
없습니다.** 색은 프로퍼티가 아니라 **컴포넌트 이름**에 들어가 있습니다 —
`Icon button togglable - tonal`, `- outline`, `- standard` 식입니다.

Figma에서는 그게 자연스럽습니다. 색마다 상태가 다섯씩 딸려 오니 한 컴포넌트에 색과
상태를 곱으로 넣으면 판이 감당하지 못합니다. **웹에서는 반대**입니다 — 상태는 브라우저가
만들고 색은 클래스 하나라, 축으로 두는 편이 자연스럽습니다. 아래 데모가 그쪽입니다.

### Default

<section class="sg-button-group-playground" data-icon-playground="default" aria-label="Icon button interactive demo">
  <div class="sg-button-group-playground__stage">
    <button type="button" class="wp-block-axismundi-icon-button"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">star</span><span class="screen-reader-text">Star</span></button>
    <pre class="sg-button-group-playground__markup" data-icon-markup></pre>
  </div>
  <div class="sg-button-group-playground__controls">
    <label>Type <select data-icon-control="shape"><option value="round">Round</option><option value="square">Square</option></select></label>
    <label>Size <select data-icon-control="size"><option value="xsmall">XSmall</option><option value="small" selected>Small</option><option value="medium">Medium</option><option value="large">Large</option><option value="xlarge">XLarge</option></select></label>
    <label>Width <select data-icon-control="width"><option value="narrow">Narrow</option><option value="default" selected>Default</option><option value="wide">Wide</option></select></label>
    <label>Color <select data-icon-control="color"><option value="">Filled</option><option value="tonal">Tonal</option><option value="outline">Outlined</option><option value="standard">Standard</option></select></label>
    <label>Icon <input type="text" list="icon-button-options" value="star" data-icon-control="icon" /></label>
    <label class="sg-button-group-playground__inline"><input type="checkbox" data-icon-control="disabled" /> Disabled</label>
    <label class="sg-button-group-playground__inline"><input type="checkbox" data-icon-control="focusRing" /> Show focus indicator</label>
  </div>
</section>

### Togglable

<section class="sg-button-group-playground" data-icon-playground="toggle" aria-label="Toggle icon button interactive demo">
  <div class="sg-button-group-playground__stage">
    <button type="button" class="wp-block-axismundi-icon-button" aria-pressed="false"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">star</span><span class="screen-reader-text">Favourite</span></button>
    <pre class="sg-button-group-playground__markup" data-icon-markup></pre>
  </div>
  <div class="sg-button-group-playground__controls">
    <label>Type <select data-icon-control="shape"><option value="round">Round</option><option value="square">Square</option></select></label>
    <label>Size <select data-icon-control="size"><option value="xsmall">XSmall</option><option value="small" selected>Small</option><option value="medium">Medium</option><option value="large">Large</option><option value="xlarge">XLarge</option></select></label>
    <label>Width <select data-icon-control="width"><option value="narrow">Narrow</option><option value="default" selected>Default</option><option value="wide">Wide</option></select></label>
    <label>Color <select data-icon-control="color"><option value="">Filled</option><option value="tonal">Tonal</option><option value="outline">Outlined</option><option value="standard">Standard</option></select></label>
    <label>Icon <input type="text" list="icon-button-options" value="star" data-icon-control="icon" /></label>
    <label class="sg-button-group-playground__inline"><input type="checkbox" data-icon-control="selected" /> Selected</label>
    <label class="sg-button-group-playground__inline"><input type="checkbox" data-icon-control="disabled" /> Disabled</label>
    <label class="sg-button-group-playground__inline"><input type="checkbox" data-icon-control="focusRing" /> Show focus indicator</label>
  </div>
</section>

<datalist id="icon-button-options">
  <option value="star">star</option>
  <option value="favorite">favorite</option>
  <option value="bookmark">bookmark</option>
  <option value="settings">settings</option>
  <option value="more_vert">more_vert</option>
</datalist>

`Icon(selected)`는 축입니다. Figma는 `stars`와 `stars_filled` 두 아이콘을 두는데,
**`stars_filled`는 Material Icons에 있던 것이고 Material Symbols로 넘어오면서
사라졌습니다.** 가변 폰트에는 `FILL` 축이 있으니 글리프를 바꿀 이유가 없습니다 —
togglable 데모에서 `Selected`를 켜면 같은 `star`가 채워지는 것이 그것입니다.

`State`와 `Show focus indicator`는 성격이 다릅니다. **`Disabled`만 저장되는 값**이고,
hover·focus·pressed는 표본을 실제로 가리키거나 탭하거나 누르면 나옵니다 — 브라우저가
만드는 것이라 저장할 것이 없습니다. `Show focus indicator`는 그중에서도 정적 파일이
링을 그려 보이기 위한 스위치라, 여기서는 **들여다보라고 켜 두는 용도**이고 저장하는
속성이 아닙니다.

## Button과 다른 네 지점

같은 이름의 축이 많아서 오히려 놓치기 쉬운 것들입니다.

| | Button | Icon button |
|---|---|---|
| 라벨 | 필수 | **없음** |
| Small 아이콘 | 20dp | **24dp** |
| spring | 0.9 / 1400 | **{{ site.data.icon_button.meta.spring_damping }} / {{ site.data.icon_button.meta.spring_stiffness }}** |
| width 축 | 없음 | **narrow · default · wide** |
| 색 스타일 | filled·tonal·outlined·elevated·text | filled·tonal·outlined·**standard** |

**Small의 아이콘이 24dp인 것**이 특히 함정입니다. 같은 "Small"인데 Button은 20dp를
씁니다. 옮겨 적다 틀린 값이 아니라 발행된 값이 다릅니다.

**spring이 더 무릅니다.** damping 0.6은 Button의 0.9보다 낮아 더 튀고, stiffness 800은
1400보다 느립니다. 작은 컨트롤이 더 큰 overshoot을 감당할 수 있다는 판단입니다.

## 두 variant

| Variant | M3 | M3 Expressive |
|---|---|---|
{% for v in site.data.icon_button.variants -%}
| **{{ v.title }}** | {% if v.m3 %}있음{% else %}—{% endif %} | {% if v.expressive %}있음{% else %}—{% endif %} |
{% endfor %}

Button과 달리 **toggle이 M3 기본에도 있습니다.** Button은 Expressive에서야 toggle이
정식이 됩니다.

<div class="sg-demo">
  <button type="button" class="wp-block-axismundi-icon-button"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">add</span><span class="screen-reader-text">Add</span></button>
  <button type="button" class="wp-block-axismundi-icon-button is-style-tonal"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">edit</span><span class="screen-reader-text">Edit</span></button>
  <button type="button" class="wp-block-axismundi-icon-button is-style-outline"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">share</span><span class="screen-reader-text">Share</span></button>
  <button type="button" class="wp-block-axismundi-icon-button is-style-standard"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">more_vert</span><span class="screen-reader-text">More</span></button>
</div>

## Configurations

| Category | Options | M3 | M3 Expressive |
|---|---|---|---|
{% for c in site.data.icon_button.configurations -%}
| {{ c.category }} | {{ c.option }} | {% if c.m3 %}있음{% else %}—{% endif %} | {% if c.expressive %}있음{% else %}—{% endif %} |
{% endfor %}

## Color — 넷이고, 하나는 container가 없습니다

Button의 다섯에서 Text와 Elevated가 빠지고 **Standard**가 들어옵니다.

| Style | Default | Toggle unselected | Toggle selected |
|---|---|---|---|
{% for c in site.data.icon_button.colors -%}
| **{{ c.title }}** | {% if c.container %}`{{ c.container }}`{% if c.container_is_outline %} (outline){% endif %} / {% endif %}`{{ c.content }}` | {% if c.toggle_unselected.container %}`{{ c.toggle_unselected.container }}` / {% endif %}`{{ c.toggle_unselected.content }}` | {% if c.toggle_selected.container %}`{{ c.toggle_selected.container }}` / {% endif %}`{{ c.toggle_selected.content }}` |
{% endfor %}

**Standard는 쉬는 상태에 container가 없습니다.** state layer가 유일하게 상자를 그리는
순간이고, 그래서 M3가 [Button group]({{ '/components/button-groups/' | relative_url }})에
standard icon button을 쓰지 말라고 하는 것입니다 — 세그먼트가 이음매와 선택을 칠할
자리를 잃습니다. 그 금지가 여기서 나옵니다.

## Width — 이 컴포넌트에만 있는 축

**width는 아이콘 좌우 여백을 바꾸지 아이콘을 바꾸지 않습니다.**

{% assign sizes = site.data.icon_button.sizes -%}

| | {% for s in sizes %}{{ s.label }} | {% endfor %}
|---|{% for s in sizes %}---|{% endfor %}
| Container height | {% for s in sizes %}{{ s.height }}dp | {% endfor %}
| Icon size | {% for s in sizes %}{{ s.icon }}dp | {% endfor %}
| Narrow 좌우 | {% for s in sizes %}{{ s.space.narrow }}dp | {% endfor %}
| Default 좌우 | {% for s in sizes %}{{ s.space.default }}dp | {% endfor %}
| Wide 좌우 | {% for s in sizes %}{{ s.space.wide }}dp | {% endfor %}
| Outline width | {% for s in sizes %}{{ s.outline_width }}dp | {% endfor %}

여기에 규칙이 하나 숨어 있습니다. **default 폭이 언제나 컨테이너 높이와 같습니다** —
{% for s in sizes %}{{ s.icon }}+{{ s.space.default }}×2={{ s.height }}{% unless forloop.last %}, {% endunless %}{% endfor %}.
그래서 기본 round icon button이 길쭉한 알약이 아니라 **원**입니다. narrow는 높이보다
좁고, wide는 넓습니다.

**단, XS와 S는 예외입니다.** 아래 48dp 타깃 규칙이 폭을 48로 밀어올리므로 실측이
48×32와 48×40이 되고, 원이 아닙니다. 규칙 둘이 부딪히면 접근성 쪽이 이기는 것이
맞지만, 그 대가로 그 두 크기에서는 M3 그림의 동그란 모양이 나오지 않습니다.

<div class="sg-demo sg-demo--wrap">
{%- for s in sizes %}
  <button type="button" class="wp-block-axismundi-icon-button" data-size="{{ s.name }}" data-width="narrow"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">star</span><span class="screen-reader-text">{{ s.label }} narrow</span></button>
  <button type="button" class="wp-block-axismundi-icon-button" data-size="{{ s.name }}"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">star</span><span class="screen-reader-text">{{ s.label }} default</span></button>
  <button type="button" class="wp-block-axismundi-icon-button" data-size="{{ s.name }}" data-width="wide"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">star</span><span class="screen-reader-text">{{ s.label }} wide</span></button>
{%- endfor %}
</div>

### Corner radius

| | {% for s in sizes %}{{ s.label }} | {% endfor %}
|---|{% for s in sizes %}---|{% endfor %}
| Round | {% for s in sizes %}Full | {% endfor %}
| Square | {% for s in sizes %}{{ s.shape_square }}dp | {% endfor %}
| Pressed | {% for s in sizes %}{{ s.pressed_morph }}dp | {% endfor %}
| Selected (round 기준) | {% for s in sizes %}{{ s.selected_round }}dp | {% endfor %}

Button의 모서리 표와 **완전히 같습니다.** 두 컴포넌트가 같은 shape 스케일 위에 있다는
뜻이고, 그래서 나란히 놓아도 어긋나지 않습니다.

### Target size

**XS와 S는 48×48dp 이상이어야 합니다.** 이건 반올림 주석이 아니라 유일하게 그 둘을
닿을 수 있게 만드는 규칙입니다 — default 폭에서 XS는 32, S는 40이니 **둘 다 미달**입니다.

이 어댑터는 `min-inline-size: 48px`으로 폭만 채웁니다. 실측하면 **XS 48×32, S 48×40** — 폭은 채워지고 **높이는 그대로라 48×48이 아닙니다.** 상자 밖으로 타깃을 확장하는 처리가 따로 필요하고, 지금은 없습니다 —
[Buttons]({{ '/components/buttons/' | relative_url }})에 적어둔 것과 같은 미결입니다.

## 이름은 보이지 않는 곳에 있습니다

라벨이 anatomy에 없으니 접근 가능한 이름이 갈 곳은 하나입니다.

```html
<button class="wp-block-axismundi-icon-button">
  <span class="material-symbols-outlined" aria-hidden="true">add</span>
  <span class="screen-reader-text">Add</span>
</button>
```

Button에서는 라벨을 숨기는 것이 **선택**이었습니다. 여기서는 **유일한 방법**입니다.

M3가 웹에 추가로 요구하는 것이 있습니다.

> On web, display a tooltip describing the action while hovering

`axismundi/theme-switcher`가 `showTooltips`를 가진 이유가 이것이고, 아이콘만 있는
컨트롤에서 툴팁은 장식이 아니라 **보는 사람을 위한 이름**입니다. 스크린 리더는
`screen-reader-text`로 읽고, 눈으로 보는 사람은 툴팁으로 읽습니다.

## Toggle과 FILL 축

> In toggle buttons, use the **outlined** style of an icon for the unselected
> state, and the **filled** style for the selected state.

Figma는 아이콘을 두 개 둡니다. 웹은 **글리프 하나에 축 하나**입니다 —
[Button groups]({{ '/components/button-groups/' | relative_url }})에서 이미 쓴 그
`--md-icon-fill`이고, 테마가 `@property`로 등록해 두어 보간됩니다.

<div class="sg-demo">
  <button type="button" class="wp-block-axismundi-icon-button" aria-pressed="false"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">star</span><span class="screen-reader-text">Unselected</span></button>
  <button type="button" class="wp-block-axismundi-icon-button" aria-pressed="true"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">star</span><span class="screen-reader-text">Selected</span></button>
  <button type="button" class="wp-block-axismundi-icon-button is-style-standard" aria-pressed="false"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">favorite</span><span class="screen-reader-text">Unselected</span></button>
  <button type="button" class="wp-block-axismundi-icon-button is-style-standard" aria-pressed="true"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">favorite</span><span class="screen-reader-text">Selected</span></button>
</div>

선택되면 모양도 바뀝니다 — round는 square가 되고, **원래 square였다면 round가
됩니다.** 방향이 아니라 대비가 신호라는 것은 Button과 같습니다.

## 테마스위처가 이미 둘을 가지고 있습니다

이 저장소에 아이콘 버튼이 둘 있고, **하나는 toggle이 아닙니다.**

```
cycle 버튼    아이콘 버튼이되 toggle 아님    auto → light → dark 순환
group 세그먼트  toggle icon button          aria-pressed
```

cycle 버튼이 `aria-pressed`를 쓰지 않는 이유가 여기서 분명해집니다. **`aria-pressed`는
이진값인데 상태가 셋**이라, 어느 하나를 반드시 잘못 보고하게 됩니다. M3의 toggle icon
button은 두 상태 컴포넌트이고, 세 상태를 순환하는 컨트롤은 그것이 아닙니다.

같은 이유로 cycle 버튼의 FILL도 다르게 걸립니다 — 세그먼트는 hover에서도 채워지지만,
cycle은 **선택된 스킴이 있을 때만** 채워집니다. 혼자 서 있어 비교 대상이 없으니 fill이
선택 상태 전부를 감당해야 하기 때문입니다.

자세한 것은 [Theme Switcher]({{ '/components/theme-switcher/' | relative_url }})에
있습니다.

## WordPress에는 아직 없습니다

`core/icon-button`은 존재하지 않고, `core/buttons`는 `allowedBlocks`가
`["core/button"]` 하나뿐이라 만들어져도 그대로는 들어가지 못합니다. M3는 그룹 안에
아이콘 버튼을 섞으라고 하는데 — "Mix and match buttons and icon buttons" — 코어가
막고 있는 지점입니다.

그때까지 이 페이지는 계약이고, 실제로 아이콘 버튼이 필요한 곳은 각자의 플러그인 블록이
자기 것을 냅니다. 테마스위처가 그 첫 사례입니다.
