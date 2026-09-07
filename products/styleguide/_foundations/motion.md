---
title: Motion
description: 스프링을 웹으로 옮긴 curve와, 축이 갈리는 이유
order: 40
lang: ko
---

이 페이지의 점들은 **자기가 설명하는 토큰으로 움직입니다.** 테마에서 curve를
다시 조율하면 여기 데모가 따라옵니다. 그래서 SMIL을 쓰지 않았습니다 — `<animate>`는
CSS 커스텀 프로퍼티를 읽지 못해서 지속시간을 리터럴로 박아야 하고, 그러면 문서가
구현과 갈라집니다.

루프도 돌지 않습니다. 레퍼런스 페이지에서 영원히 움직이는 것은 읽기 어렵고
reduced-motion 문제이기도 해서, 재생은 독자가 누릅니다.

## 두 축

M3 Expressive의 모션은 **스프링**입니다. CSS는 스프링을 돌릴 수 없어서, 테마는
M3가 웹용으로 발행한 변환을 씁니다 — 각 `curve-*`는 이미 cubic-bezier와 지속시간
한 쌍으로 바뀐 스프링입니다.

**그 둘은 하나의 값입니다.** 곡선만 가져다 다른 지속시간에 붙이면 원래 스프링이
아닙니다.

축이 갈리는 것도 이유가 있습니다.

```
spatial   기하 — 위치, 크기, 모양, 회전      오버슈트함
effects   페인트 — 색, 불투명도               오버슈트하지 않음
```

아래에서 spatial 점들이 끝에서 살짝 넘어갔다 돌아오는 것을 보실 수 있습니다.
cubic-bezier의 y 제어점이 1을 넘기 때문입니다.

### Spatial

<button class="sg-play" type="button" data-sg-motion-play aria-controls="motion-spatial">재생</button>

<ul class="sg-motion" id="motion-spatial">
  <li class="sg-motion__row">
    <span class="sg-motion__track"><span class="sg-motion__dot" style="--sg-motion-curve: var(--md-sys-motion-curve-fast-spatial); --sg-motion-duration: var(--md-sys-motion-curve-fast-spatial-duration)"></span></span>
    <span class="sg-specimen__meta"><code class="sg-specimen__token">curve-fast-spatial</code><span>350ms</span><span>cubic-bezier(0.42, 1.67, 0.21, 0.9)</span></span>
  </li>
  <li class="sg-motion__row">
    <span class="sg-motion__track"><span class="sg-motion__dot" style="--sg-motion-curve: var(--md-sys-motion-curve-default-spatial); --sg-motion-duration: var(--md-sys-motion-curve-default-spatial-duration)"></span></span>
    <span class="sg-specimen__meta"><code class="sg-specimen__token">curve-default-spatial</code><span>500ms</span><span>cubic-bezier(0.38, 1.21, 0.22, 1)</span></span>
  </li>
  <li class="sg-motion__row">
    <span class="sg-motion__track"><span class="sg-motion__dot" style="--sg-motion-curve: var(--md-sys-motion-curve-slow-spatial); --sg-motion-duration: var(--md-sys-motion-curve-slow-spatial-duration)"></span></span>
    <span class="sg-specimen__meta"><code class="sg-specimen__token">curve-slow-spatial</code><span>650ms</span><span>cubic-bezier(0.39, 1.29, 0.35, 0.98)</span></span>
  </li>
</ul>

### Effects

<button class="sg-play" type="button" data-sg-motion-play aria-controls="motion-effects">재생</button>

<ul class="sg-motion" id="motion-effects">
  <li class="sg-motion__row">
    <span class="sg-motion__track"><span class="sg-motion__dot" style="--sg-motion-curve: var(--md-sys-motion-curve-fast-effects); --sg-motion-duration: var(--md-sys-motion-curve-fast-effects-duration)"></span></span>
    <span class="sg-specimen__meta"><code class="sg-specimen__token">curve-fast-effects</code><span>150ms</span><span>cubic-bezier(0.31, 0.94, 0.34, 1)</span></span>
  </li>
  <li class="sg-motion__row">
    <span class="sg-motion__track"><span class="sg-motion__dot" style="--sg-motion-curve: var(--md-sys-motion-curve-default-effects); --sg-motion-duration: var(--md-sys-motion-curve-default-effects-duration)"></span></span>
    <span class="sg-specimen__meta"><code class="sg-specimen__token">curve-default-effects</code><span>200ms</span><span>cubic-bezier(0.34, 0.8, 0.34, 1)</span></span>
  </li>
  <li class="sg-motion__row">
    <span class="sg-motion__track"><span class="sg-motion__dot" style="--sg-motion-curve: var(--md-sys-motion-curve-slow-effects); --sg-motion-duration: var(--md-sys-motion-curve-slow-effects-duration)"></span></span>
    <span class="sg-specimen__meta"><code class="sg-specimen__token">curve-slow-effects</code><span>300ms</span><span>cubic-bezier(0.34, 0.88, 0.34, 1)</span></span>
  </li>
</ul>

effects 쪽이 눈에 띄게 짧습니다. 색이나 불투명도는 자리를 옮기지 않으므로 오래
끌 이유가 없고, 오버슈트할 것도 없습니다.

## 옛 토큰이 남아 있는 이유

duration 16단계와 easing 7종은 그대로 있습니다. 폐기된 것이 아니라 **아직 필요한
자리가 있어서**입니다.

**반복되는 keyframe 애니메이션은 curve를 받을 수 없습니다.** curve의 지속시간이
값의 일부라서, 곡선만 떼어 쓰면 짝을 잃습니다. 그런 자리에서는 easing과 duration을
따로 골라 씁니다.

```
--md-sys-motion-duration-short1 … extra-long4      50ms … 1000ms
--md-sys-motion-easing-linear / standard / emphasized
                (+ -accelerate, -decelerate)
```

`easing-standard`와 `easing-emphasized`는 값이 같습니다 — 둘 다
`cubic-bezier(0.2, 0, 0, 1)`. M3가 그렇게 발행합니다.

## 실제 사용처

이 사이트의 테마 스위처가 두 축을 함께 씁니다.

```css
transition:
  border-radius     var(--md-sys-motion-curve-fast-spatial-duration)
                    var(--md-sys-motion-curve-fast-spatial),
  background-color  var(--md-sys-motion-curve-fast-effects-duration)
                    var(--md-sys-motion-curve-fast-effects);
```

모서리는 기하라 spatial, 상태 레이어 색은 페인트라 effects입니다. 눌렀을 때
모양이 먼저 튀고 색이 빠르게 따라오는 게 그 결과입니다.

아이콘의 `FILL` 축도 `curve-fast-effects`로 보간됩니다 — `icons.css`가
`--md-icon-fill`을 `@property`로 등록해 두었기 때문에 축이 뚝 끊기지 않고
이어집니다.

## reduced-motion

`prefers-reduced-motion: reduce`에서는 이 페이지의 데모도, 스위처의 전환도
`transition: none`이 됩니다. 애니메이션을 줄여 달라는 것은 취향 표명이 아니라
접근성 요구입니다.
