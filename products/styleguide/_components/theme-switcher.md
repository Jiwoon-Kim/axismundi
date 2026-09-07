---
title: Theme Switcher
description: 색 구성표를 auto / light / dark 사이에서 전환하는 Axismundi 컨트롤
kind: axismundi
order: 10
lang: ko
---

Material 컴포넌트가 아닙니다. M3의 **Icon button**과 **Connected button group**을
쓰지만, `auto / light / dark`의 의미, 저장, 첫 페인트 전 복원, 사이트 전역 토큰
전환은 전부 Axismundi 제품 고유의 책임입니다. M3 사양에 이런 컴포넌트는 없습니다.

WordPress에서는 [`axismundi-theme-switcher`]({{ site.repository_url }}/tree/main/products/wordpress/plugins/axismundi-theme-switcher)
블록 플러그인으로 배포됩니다. 이 페이지의 것은 그 플러그인의 **정적 어댑터**이고,
지금 이 사이트의 좌측 상단에서 실제로 쓰이고 있는 컨트롤이기도 합니다.

## 두 표면, 하나의 controller

동작 로직은 하나이고, UI와 ARIA만 각자 역할에 맞게 가집니다.

### Cycle

{% include components/theme-switcher-cycle.html %}

아이콘 버튼 하나가 `auto → light → dark → auto`로 순환합니다. 좁은 자리에
들어가야 할 때 쓰고, 이 사이트의 shell이 쓰는 것도 이쪽입니다.

**`aria-pressed`를 쓰지 않습니다.** 이 속성은 이진값인데 컨트롤의 상태는 셋이라,
어느 하나를 반드시 잘못 보고하게 됩니다. 대신 접근 가능한 이름이 상태를
운반하고, 바뀔 때마다 다시 씁니다.

```
aria-label="Color scheme: Dark. Activate to cycle."
```

### Group

{% include components/theme-switcher-group.html %}

세 세그먼트 중 하나가 눌린 상태입니다. 이쪽은 **진짜 토글 집합**이므로
`aria-pressed`를 의도대로 씁니다.

위의 두 컨트롤은 같은 controller를 소비하고 같은 이벤트를 듣습니다. 하나를
조작하면 다른 하나가 즉시 따라오는 것을 여기서 직접 확인할 수 있습니다 — 서로의
존재는 모릅니다.

라벨을 숨긴 변형도 있습니다. 라벨 문자열은 사라지지 않고 screen-reader 텍스트로
이동하므로, 보이든 안 보이든 접근 가능한 이름의 출처는 하나입니다.

{% include components/theme-switcher-group.html labels=false %}

## 상태 계약

```
html[data-theme]   "auto" | "light" | "dark"   항상 존재, 지우지 않음
                   인식하지 못하는 값 → "auto"
저장               localStorage "axismundi_theme"
부트스트랩          <head> 최상단 블로킹 스크립트, 토큰 CSS보다 먼저
방송               window "axismundi-theme-scheme-change", detail.mode
```

`auto`는 **속성을 지우는 것이 아니라 기록되는 값**입니다. 테마의 다크 CSS가
`:root:not([data-theme])`와 `[data-theme="auto"]`를 같은 블록에서 처리하므로
렌더 결과는 같지만, 속성을 지우면 "auto를 선택함"과 "선택한 적 없음"이
구분되지 않습니다. 저장이 의미를 갖는 지점이 정확히 거기입니다.

부트스트랩이 서버 렌더가 아닌 것도 이유가 있습니다. 플러그인 쪽 주석이
말하듯, 전면 페이지 캐시가 한 방문자의 색 모드를 공유 HTML에 구워버리면 안
됩니다. 정적 사이트는 그 문제의 가장 강한 형태이고, localStorage는 우회가 아니라
같은 문제의 같은 해법입니다.

## 상호작용

State layer, focus ring, 모양 morph는 전부 테마에서 동기화한 토큰을 소비합니다.

```
hover          @media (hover: hover) 안에서만
focus-visible  focus state layer + ring
:focus:not(:focus-visible)   outline: none
:active        pressed state layer + 모서리 morph
tap            -webkit-tap-highlight-color: transparent
reduced-motion transition: none
```

hover를 게이팅하는 이유는 터치에서 hover가 손을 뗀 뒤에도 눌러붙기 때문이고,
그 자리는 이미 pressed state layer가 답합니다. 탭 하이라이트를 없앤 이유는
브라우저가 그리는 사각형이 pill과 모양도 색도 맞지 않아서인데, 잃는 것은
없습니다 — 키보드용 링은 `:focus-visible`이 그대로 그립니다.

## 이식하지 않은 것

플러그인의 WordPress 배관은 가져오지 않았습니다. Interactivity API store,
`wp_interactivity_state` 서버 시딩, `data-wp-*` 디렉티브, 편집기 iframe 브리지는
전부 WordPress의 문제이지 이 컨트롤의 계약이 아닙니다.

툴팁도 아직 없습니다. cycle 버튼의 동적 `aria-label`이 정확하면 접근성 계약은
성립하고, 툴팁은 그 자체로 별도 컴포넌트라 그때 함께 들어오는 편이 깔끔합니다.
