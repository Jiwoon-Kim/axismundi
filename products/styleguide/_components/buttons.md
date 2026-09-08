---
title: Buttons
description: 다섯 색 스타일과 다섯 크기, 그리고 그 둘이 서로를 구속하지 않는 이유
kind: material
order: 20
lang: ko
---

M3의 button은 **두 개의 독립 축**을 가집니다. 색 스타일이 크기를 정하지 않고,
크기가 색 스타일을 정하지 않습니다. 이 페이지의 표가 하나의 행렬이 아니라 두 개의
목록인 이유이고, [WordPress 바인딩]({{ '/wordpress/button-and-buttons/' | relative_url }})
쪽에서 크기를 `is-style-*`로 만들지 않은 이유이기도 합니다.

이 페이지의 모든 버튼은 실제로 동작합니다. 마크업은 블록 에디터가 쓰는 것과 같은
`.wp-block-button > .wp-block-button__link`이고, CSS는 테마의 계약을 정적으로
재진술한 것입니다.

## Anatomy

```
Container      필수. outlined·text에서는 쉬는 상태에 보이지 않음
Label text     필수
Icon           선택
```

## 두 변형

**Default button**과 **Toggle button**입니다. 같은 컴포넌트의 두 모드가 아니라
**색이 서로 다른 두 변형**입니다 — 아래 색 표의 열이 셋인 이유입니다.

토글에는 **text 스타일이 없습니다.** 선택 상태를 알릴 container가 없으니
선택/비선택을 색으로 구분할 방법이 사라집니다.

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
    <div class="wp-block-button is-style-tonal is-shape-square"><button type="button" class="wp-block-button__link wp-element-button">Square</button></div>
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
    <div class="wp-block-button{% unless s.name == 'small' %} is-size-{{ s.name }}{% endunless %}"><button type="button" class="wp-block-button__link wp-element-button">{{ s.label }}</button></div>
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

**하나의 button에서는 button이 크기와 스타일을 정합니다.** 하나의 group에서는
group이 정합니다 — group의 구성원은 독립된 button이 아니라 **segment**이기 때문입니다.

이 규칙이 CSS와 블록 마크업에서 실제로 어떻게 성립하는지는 바인딩 페이지에 있습니다.
