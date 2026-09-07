---
title: Shape
description: 두 계열로 나뉜 corner 토큰과, 그 구분이 실제로 하는 일
order: 50
lang: ko
---

M3는 모서리 토큰을 두 계열로 발행하고, **그 차이는 장식이 아닙니다.**

```
corner-value.*   유한한 길이.  전환의 양 끝으로 안전
corner.*         완전한 border-radius. 원형 full 포함
```

## corner-value에 full이 없는 이유

의도적으로 없습니다. **pill 반지름은 길이가 아닙니다.**

브라우저는 상자의 절반을 넘는 반지름을 상자에 맞게 축소합니다. 그래서 40px
컨트롤에서 `9999px → 8px`로 애니메이션하면, 지속시간의 99.8% 동안 pill이 그대로
있다가 마지막에 툭 끊깁니다.

**모양 morph는 반드시 `corner-value`에서 시작해 `corner-value`로 끝나야 합니다.**

이 사이트의 테마 스위처가 그 규칙을 따릅니다 — 세그먼트를 누르면 안쪽 모서리가
`corner-value-small`에서 `corner-value-extra-small`로 줄어들고, 그룹의 바깥
모서리만 pill로 남습니다.

## Corner values

전환 끝점으로 쓸 수 있는 유한한 길이들입니다.

<ul class="sg-shapes">
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-value-none)">0</span><span class="sg-shape__name">value-none</span></li>
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-value-extra-small)">4px</span><span class="sg-shape__name">value-extra-small</span></li>
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-value-small)">8px</span><span class="sg-shape__name">value-small</span></li>
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-value-medium)">12px</span><span class="sg-shape__name">value-medium</span></li>
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-value-large)">16px</span><span class="sg-shape__name">value-large</span></li>
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-value-large-increased)">20px</span><span class="sg-shape__name">value-large-increased</span></li>
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-value-extra-large)">28px</span><span class="sg-shape__name">value-extra-large</span></li>
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-value-extra-large-increased)">32px</span><span class="sg-shape__name">value-extra-large-increased</span></li>
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-value-extra-extra-large)">48px</span><span class="sg-shape__name">value-extra-extra-large</span></li>
</ul>

## Corner shapes

같은 값을 완전한 `border-radius`로 감싼 것들과, 길이가 아닌 `full` 하나.

<ul class="sg-shapes">
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-medium)">medium</span><span class="sg-shape__name">corner-medium</span></li>
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-extra-large)">extra-large</span><span class="sg-shape__name">corner-extra-large</span></li>
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-full)">full</span><span class="sg-shape__name">corner-full</span></li>
  <li><span class="sg-shape__box" style="border-radius: var(--md-sys-shape-corner-large-top)">large-top</span><span class="sg-shape__name">corner-large-top</span></li>
</ul>

## 방향 토큰

`*-top`은 발행하고 `*-start` / `*-end`는 **일부러 없습니다.**

커스텀 프로퍼티는 물리적 축약형만 담을 수 있는데, 그건 RTL에서 틀립니다. 그렇다고
`html[dir="rtl"]`로 덮으면 트리 아래쪽에서 `dir`이 다시 설정된 경우를 놓칩니다.

한쪽만 둥글게 하려면 **논리 longhand를 직접 쓰는 것**이 정답입니다.

```css
border-start-start-radius: var(--md-sys-shape-corner-value-large-increased);
border-end-start-radius: var(--md-sys-shape-corner-value-large-increased);
```

`*-top`이 괜찮은 이유는 블록 축이 쓰기 방향에 의존하지 않기 때문입니다.

## 스케일 전체가 발행되는 이유

모션 토큰과 달리, shape은 테마가 오늘 쓰는 것만이 아니라 **스케일 전체**를
내보냅니다. 플러그인 블록들이 이걸 계약으로 소비하는데, 반쪽짜리 스케일은 계약이
아니기 때문입니다.

## core 블록은 리터럴을 씁니다

WordPress에는 편집기에 노출할 radius preset 스케일이 없습니다. 그래서 core 블록의
모서리는 `theme.json`에 리터럴로 남아 있고, 이 토큰들은 테마의 구조 CSS와
플러그인의 커스텀 블록을 위한 것입니다.

색·타입스케일과 다른 지점이라 적어 둡니다 — WordPress가 preset 모델을 제공하지
않는 축에서는 토큰이 CSS에만 존재합니다. [State]({{ '/foundations/state/' | relative_url }})도
같은 처지입니다.
