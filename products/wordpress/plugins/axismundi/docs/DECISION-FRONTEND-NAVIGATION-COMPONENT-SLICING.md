# 결정 기록: Social Frontend — 레이아웃 컴포넌트를 어떤 단위로 자를 것인가

## 상태

**채택됨. 2026-10-04.** 소유자 판정.

`PLAN-FRONTEND-NAVIGATION-COMPONENTS.md`는 "아이디어 기록, 채택된 것 없음"이며, 결정이
생기면 별도 기록을 만들라고 적어 두었다. 이 문서가 그것이다. 그 계획의 아이디어 3·4에
대한 판정을 담고, 나머지 아이디어는 여전히 미채택이다.

이 문서는 **순서와 슬라이스 경계**만 다룬다. navigation data와 URL 거동은
`DECISION-FRONTEND-NAVIGATION-MODEL.md`가, window size class는
`DECISION-FRONTEND-ADAPTIVE-LAYOUT.md`가 이미 소유한다.

## 1. 순서의 기준 — 레이아웃을 구성할 수 있게 되는가

Material 컴포넌트를 더 만들 때, 다음 후보를 고르는 기준은 **그것이 레이아웃을 구성
가능하게 만드는가**이다. "다른 컴포넌트의 경계 문제를 같이 해소해 주는가"는 기준이 아니다.

그 이유는 이 저장소의 과거 선택이 이미 증명한다. SplitButton의 trailing ref 경계를 Menu가
해소한다는 이유로 Menu를 먼저 할 거라면, IconButton을 할 때 Tooltip을 먼저 했어야 했다.
하지 않았다. 그러므로 "경계 해소"는 이 프로젝트가 실제로 써 온 순서 기준이 아니다.

레이아웃이 먼저여야 하는 이유는 연쇄가 거기서 시작하기 때문이다. app bar · navigation bar ·
navigation rail이 있어야 Scaffold의 밴드가 실체를 갖고, 그 다음에 pane · rail · bar를
배치하는 작업이 가능해진다.

## 2. 측정된 현황 — foundation은 이미 있다

2026-10-04 측정. 브레이크포인트를 새로 만들 필요가 없다. 비어 있는 것은 점유자다.

```txt
foundations/layout/breakpoints/viewport.json
  compact 0-599 · medium 600-839 · expanded 840-1199 · large 1200-1599 · extraLarge 1600+
  (M3의 다섯 window size class가 그대로 선언돼 있다)

foundations/layout/scaffold/index.js
  슬롯: navigationBar · navigationRail · supporting · children(main)
  app bar 슬롯은 없다

foundations/layout/scaffold/scaffold.css
  @media (min-width: 600px)  rail 표시, bar 숨김
  @media (min-width: 840px)  supporting pane 배치
  1200 / 1600 분기는 없다
```

`Scaffold`의 주석은 이미 경계를 올바르게 선언한다 — "owns window-level geometry only;
callers supply the actual bar, rail, and supporting components." 이 결정은 그 경계를
바꾸지 않는다.

### 1200 / 1600 분기는 rail과 함께 온다

M3는 large·extra-large에서 rail이 expanded가 되거나 drawer를 대체하기를 기대한다. 그래서
빠진 두 분기는 Scaffold가 혼자 채울 수 없고, rail 컴포넌트가 expanded 상태를 가질 때 같이
따라온다. Scaffold에만 미디어 쿼리를 더하는 것은 하지 않는다.

## 3. navigation bar와 rail은 한 슬라이스다

둘을 따로 출하하지 않는다.

`scaffold.css`가 이미 600에서 둘을 교대시킨다. 하나만 만들면 **어느 한쪽 폭에서
내비게이션이 아예 없는 상태**가 된다 — bar만 있으면 600 이상에서 사라지고, rail만 있으면
600 미만에서 없다. M3도 이 둘을 size class에 따른 하나의 내비게이션 선택으로 정의하고,
담당 범위가 medium에서 겹친다(`PLAN` 참조).

이것은 `PLAN`의 아이디어 3을 채택한다는 뜻이 **아니다.** 그 아이디어는 orientation prop
하나로 두 컴포넌트를 전환하자는 것이었고, 같은 문서가 그것이 M3와 1:1로 맞지 않는다고 이미
적어 두었다 — rail은 collapsed/expanded, expanded는 modal/non-modal이라는 별도 축을 더
갖는다. 여기서 정하는 것은 **출하 단위가 둘을 함께 포함한다**는 것뿐이고, 내부 구조는
구현하며 측정해서 정한다.

## 4. route 자동생성은 붙이지 않는다 — `PLAN` 아이디어 4 판정

navigation 컴포넌트는 목적지를 **명시 배열로 받는다.**

```js
destinations = [ { id, label, icon, href }, … ]
```

세 가지 이유로 지금 자동생성을 붙이면 손해다.

1. **라우트 IA가 아직 열려 있다.** Actors의 `/actors/{uuid}` 대 axismundi의 `/social/@handle`
   분할이 미결이고, Object 읽기 표면의 식별자 모양(`DECISION-OBJECT-READ-SURFACES.md` §7-B)도
   미결이다. 자동생성은 정해지지 않은 경로 모양을 컴포넌트에 굳힌다.
2. **목적지는 라우트 표에서 도출되지 않는다.** 라우트는 수십 개가 되고 M3는 bar를 3-5,
   rail을 3-7로 제한한다. 무엇을 올릴지는 제품 결정이지 파생값이 아니다. `PLAN`이 이미
   같은 지점을 지적했다 — registry를 그대로 흘리면 `stylebook/*`가 사용자 내비게이션에
   올라온다.
3. **나중에 붙이는 비용이 낮다.** 위 계약을 지키면 생성기가 그 배열을 만들어 넣으면 되고
   컴포넌트는 건드리지 않는다.

`PLAN` 아이디어 4의 실질이 "route registry를 만들고 라우터가 그것을 읽게 하자"이고 그것이
이미 결정된 항목이라는 분석은 유효하다. 이 결정은 그 순서를 뒤집지 않는다 — registry는
`DECISION-FRONTEND-NAVIGATION-MODEL.md`의 일정에 남고, navigation 컴포넌트는 registry를
기다리지 않는다.

## 5. 채택된 순서

```txt
1. navigation bar + navigation rail      한 슬라이스. 600 전환을 실체화하고,
                                         rail의 expanded가 1200/1600 분기를 가져온다
2. top app bar                           독립적. Scaffold에 슬롯 추가가 함께 간다
3. pane / canonical layout               supporting 슬롯은 있고, 일은 pane 거동이다
```

기존 패턴대로 각 슬라이스는 `products/styleguide/_data/*.yml` → validator → 컴포넌트 →
스타일북 페이지 순서로 간다.

## 6. 미결

- **nav item primitive의 소유 경계.** bar와 rail 둘 다 아이콘 + 라벨 + active indicator +
  badge를 가진 item을 필요로 한다. 이것은 M3 List가 아니고 별개다. 테마에는 이미 M3 item
  baseline(pill shape, state layer, indicator)이 있지만 그것은 블록 테마 쪽 구현이고, React
  앱은 자기 것을 갖는다. **두 구현을 공유 소유로 적지 않는다** — 관련 경계는
  `axismundi-navigation-icons` 쪽에 이미 기록돼 있다.
- **이름 축 두 개.** container 방향(core/navigation의 orientation)과 item 안의 icon/label
  배치(M3의 horizontal/vertical item)는 서로 다른 축이다. 어느 이름을 어느 축에 줄지는
  `PLAN` 아이디어 3이 미결로 남겼고 여기서도 정하지 않는다.
- **rail의 collapsed / expanded / modal 축.** expanded rail이 navigation drawer를
  대체하므로, drawer를 별도 컴포넌트로 둘 것인지가 함께 걸린다.
- **M3 Specs / Accessibility 표.** `SOURCE-M3-NAVIGATION-RAW.md`에 bar·rail 1차 자료가 있으나
  Specs·Accessibility 탭은 아직 없다. m3.material.io는 CSR이라 직접 읽으면 토큰 표를 놓친다
  — 구현 착수 시 소유자가 붙여 주는 쪽이 정확하다.

## 관련 기록

- `PLAN-FRONTEND-NAVIGATION-COMPONENTS.md` — 아이디어 기록. 3·4는 이 문서가 판정했다
- `DECISION-FRONTEND-NAVIGATION-MODEL.md` — navigation data·URL 거동 (채택됨)
- `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md` — window size class, pane 어휘
- `DECISION-FRONTEND-COMPONENT-CONVENTIONS.md` — 새 컴포넌트 착수 전 규약
- `SOURCE-M3-NAVIGATION-RAW.md` — 1차 자료
