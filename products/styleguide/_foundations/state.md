---
title: State
description: state layer 불투명도와 focus ring — 한 컴포넌트가 동시에 둘 다 입는다
order: 60
lang: ko
---

M3 Foundations의 Interaction에서 오는 두 가지가 한 파일에 있습니다. 둘 다 모든
상호작용 표면을 가로지르고, **둘 다 M3의 일곱 Styles 축 어디에도 속하지
않습니다.** 포커스된 컨트롤은 이 둘을 동시에 입습니다.

WordPress에는 이 둘을 발행할 preset 모델이 없습니다. 그래서 CSS가 유일한 거처입니다.

## State layer

역할 색을 컨테이너 위에 얇게 덮는 층입니다. 값은 불투명도이고, **퍼센트로
소비합니다.**

```css
background-color: color-mix(in srgb,
  var(--md-sys-color-on-surface)
  calc(var(--md-sys-state-hover-state-layer-opacity) * 100%),
  var(--md-sys-color-surface-container));
```

아래는 그 계산을 그대로 적용한 것입니다.

<ul class="sg-states">
  <li><span class="sg-state__swatch" style="--sg-state-opacity: var(--md-sys-state-hover-state-layer-opacity)">hover</span><span class="sg-shape__name">0.08</span></li>
  <li><span class="sg-state__swatch" style="--sg-state-opacity: var(--md-sys-state-focus-state-layer-opacity)">focus</span><span class="sg-shape__name">0.10</span></li>
  <li><span class="sg-state__swatch" style="--sg-state-opacity: var(--md-sys-state-pressed-state-layer-opacity)">pressed</span><span class="sg-shape__name">0.10</span></li>
  <li><span class="sg-state__swatch" style="--sg-state-opacity: var(--md-sys-state-dragged-state-layer-opacity)">dragged</span><span class="sg-shape__name">0.16</span></li>
</ul>

`focus`와 `pressed`는 값이 같습니다. 구분은 불투명도가 아니라 **포커스 링이
함께 오느냐**로 이루어집니다.

`disabled`는 이 목록에 있지만 성격이 다릅니다 — 컨테이너 위에 덮는 층이 아니라
**내용 자체에 거는 평평한 불투명도**입니다.

```
--md-sys-state-disabled-state-layer-opacity: 0.38
```

## hover는 게이팅해야 합니다

터치 화면에서 `:hover`는 손을 뗀 뒤에도 눌러붙습니다. 그래서 hover 규칙은
`@media (hover: hover)` 안에 둡니다.

```css
@media (hover: hover) {
  .control:hover { /* hover state layer */ }
}
```

`:active`(pressed)는 게이팅하지 않습니다. 터치에서 실제로 답하는 층이 그것이고,
거기가 state layer가 진짜 일하는 자리입니다.

## Focus ring

값은 Material Web의 `md-comp-focus-ring` 자체에서 읽은 것이고, 옮겨 적은 것이
아닙니다.

```
--md-focus-ring-width: 3px
--md-focus-ring-outward-offset: 2px
--md-focus-ring-inward-offset: 0px
```

Material Web은 링을 **별도 요소**로 그립니다 — outward는 `inset: -2px`에 3px
outline, inward는 `inset: 0`에 3px border. 우리는 컴포넌트 자신의 `outline`으로
그리므로 outward는 발행된 오프셋 그대로지만, inward는 링이 가장자리 바깥이 아니라
안쪽에 앉도록 **폭을 빼야** 합니다.

```css
outline-offset: calc(var(--md-focus-ring-inward-offset) - var(--md-focus-ring-width));
```

`calc`가 한 곳에만 있는 이유가 그것이고, 덕분에 두 숫자는 M3 값과 동일하게
남습니다.

## 링 색은 토큰이 아닙니다

M3는 `secondary`를 지정하고, lightbox를 뺀 모든 자리가 그걸 씁니다. lightbox는
임의의 미디어 위에 뜨므로 고정된 역할이 대비를 보장할 수 없어 `currentColor`로
링을 그립니다. 그래서 색은 토큰으로 발행하지 않습니다.

## 키보드만

```css
.control:focus-visible { outline: … }
.control:focus:not(:focus-visible) { outline: none; }
```

포인터로 누른 자리에 링이 남으면 안 됩니다. `:focus-visible`이 브라우저에게
판단을 맡기는 방식이고, 두 번째 규칙이 옛 `:focus` 동작을 정리합니다.

## 가져오지 않은 것

`active-width: 8px` over `duration-long4` — 링의 등장 애니메이션입니다. 자리마다
keyframe이 필요하고 reduced-motion 가드도 따로 필요해서, 지금 크기와 다른 변경이라
보류했습니다.

## 실제 사용처

이 사이트의 테마 스위처가 넷을 다 씁니다 — hover는 게이팅해서, focus는 링과 함께,
pressed는 모서리 morph와 함께. 자세한 것은
[Theme Switcher]({{ '/components/theme-switcher/' | relative_url }}).
