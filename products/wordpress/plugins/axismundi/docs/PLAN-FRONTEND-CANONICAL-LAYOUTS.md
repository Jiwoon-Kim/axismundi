# 계획: canonical layout을 실체화한다 — window size class 관측과 pane topology

## 상태

**계획. 2026-10-05. 채택된 것은 아래 "이미 채택된 것"뿐이다.**

이 문서의 대부분은 새 결정이 아니라 **이미 채택된 기록의 적용**이다. 그 구분을 절마다
밝힌다 — 적용은 뒤집으려면 원래 기록을 같이 바꿔야 하고, 새 판단은 소유자 판정이 남아
있다.

`DECISION-FRONTEND-ADAPTIVE-LAYOUT.md`가 어휘와 수치를 이미 소유한다. 이 문서는 그것을
**작동하게 만드는 순서**이지 다시 정하는 자리가 아니다.

## 0. 이미 채택된 것 — 다시 열지 않는다

```txt
다섯 window size class와 600 / 840 / 1200 / 1600 CSS 픽셀 임계값
breakpoint별 권장 pane 수 (compact 1 · medium 1 · expanded 2 · large 2 · extra-large 2)
layout / Material component / Axismundi component 세 층의 경계
Scaffold는 rail·bar·supporting·page 콘텐츠를 제조하지 않는다 — 슬롯만 연다
canonical-examples는 topology만 세우고 제품 결정을 하지 않는다
show-hide / levitate / reflow 는 "가능한 전략"이지 전부 구현하라는 지시가 아니다
```

출처는 `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md`, 원문은 `SOURCE-M3-ADAPTIVE-LAYOUT-RAW.md`와
`SOURCE-M3-CANONICAL-LAYOUTS-RAW.md`.

## 1. 측정된 현황 — 생각보다 많이 서 있다

2026-10-05 측정.

```txt
foundations/layout/
  breakpoints/viewport.json        다섯 클래스 선언 — 읽는 JS가 0건
  panes/pane, panes/pane-group     중립 grid primitive, 변경 불필요
  scaffold/                        appBar · navigationBar · navigationRail · supporting · children
  canonical-examples/
    feed/         FeedLayout({children})                  600 / 840 / 1200 / 1600 미디어 쿼리
    list-detail/  ListDetailLayout({list,detail,extra,compactPane})   840 / 1600
    supporting-pane/ SupportingPaneLayout({primary,supporting})       840
```

**`ListDetailLayout`이 이미 `compactPane`을 prop으로 받는다.** 상태를 소유하지 않는 올바른
모양이 이미 있고, 빠진 것은 그것을 *누가 계산하는가*와 *창을 누가 관측하는가* 둘뿐이다.

## 2. 새로 만드는 것은 하나뿐이다 — window size class 관측

```txt
foundations/layout/breakpoints/use-window-size-class.js        신규
```

```js
useWindowSizeClass() → 'compact' | 'medium' | 'expanded' | 'large' | 'extraLarge' | null
```

경계는 `viewport.json`에서 읽고 `matchMedia`로 구독한다. 그래야 그 JSON이 장식이 아니게
된다. 마운트 전에는 `null`을 돌려주고 CSS가 레이아웃을 들고 있게 한다 — 첫 페인트에
깜빡이면 안 된다.

**이 훅은 스타일링용이 아니다.** 미디어 쿼리로 표현되는 것은 미디어 쿼리로 남긴다. 훅은
**CSS가 표현할 수 없는 것**에만 쓴다. 이 경계는 nav item에서 측정으로 확인됐다 — vertical과
horizontal은 라벨이 indicator의 자식이냐 형제냐가 달라서 미디어 쿼리로 바꿀 수 없고, 그래서
JS 신호가 필요하다. 반대로 폭·간격·순서는 전부 CSS가 한다. 둘 다 쓰면 진실의 출처가 둘이
되고 드리프트한다.

이것을 기다리는 곳이 이미 셋이다.

```txt
nav bar   medium에서 horizontal item     DOM이 달라 미디어 쿼리로 불가
nav rail  collapsed / expanded 전환       1200 / 1600 분기
surface   presentation 선택               surface.yml이 breakpoint로 전환한다고 정의
```

## 3. `navigators/` 층은 만들지 않는다

**적용이지 새 결정이 아니다.** `DECISION-FRONTEND-NAVIGATION-MODEL.md`가 이미 "React 앱은
실제 anchor URL을 보존하고, 라우터가 수식되지 않은 primary click을 향상시킨다"로 채택돼
있다.

Android 예제의 "compact에서 detail이 list를 대체하고 Back이 list로 돌아간다"는 이 앱에서는
**그냥 라우팅**이다. 선택된 항목은 URL에 있고 Back은 브라우저 히스토리다. 그러므로
`compactPane`은 **"URL에 선택된 id가 있는가"에서 파생**된다.

선택 상태를 소유하는 컴포넌트를 따로 두면 URL과 두 벌이 되고, 그것은 nav item에서
`onClick`-only navigation을 금지한 것과 같은 실수다.

```txt
layout primitive   전부 무상태. 노드와 파생된 값만 받는다
route              URL에서 파생해서 내려준다
```

## 4. 공개 API — 거의 바뀌지 않는다

```js
Scaffold({ appBar, navigationBar, navigationRail, supporting, children })  변경 없음
Pane({ as, className, children })                                         변경 없음
PaneGroup({ className, children })                                        변경 없음
FeedLayout({ children })                                                  변경 없음 — 순수 CSS
ListDetailLayout({ list, detail, extra, compactPane })                    변경 없음
SupportingPaneLayout({ primary, supporting })                             변경 없음
```

`SupportingPaneLayout`에 `supportingVisible`을 붙이지 않는다. 채택된 기록이 show-hide를
"가능한 전략"으로만 두었고 현재 구현된 것은 840에서의 reflow다. 제품 요구가 생길 때 붙인다.

## 5. breakpoint × pane topology

| 클래스 | Scaffold chrome | Feed | List-detail | Supporting pane |
| --- | --- | --- | --- | --- |
| compact 0–599 | bottom bar | 1열 | **1 pane** — route가 list/detail 선택 | 세로 스택 |
| medium 600–839 | rail | 2열 | 1 pane | 세로 스택 |
| expanded 840–1199 | rail + supporting | 적응형 그리드, 카드 최소 240 | **2 pane** | 가로 7:3 |
| large 1200–1599 | rail | 〃 | 2 | 〃 |
| extra-large 1600+ | rail | 〃 | 2, `extra`가 있으면 **3** | 〃 |

채택된 pane 수 표와 일치한다. 현재 CSS가 이미 feed 600/840/1200/1600, list-detail 840/1600,
supporting 840을 갖고 있으므로 2단계는 **새로 쓰는 것이 아니라 재서 표와 맞는지 확인하는
일**이다.

**1200 / 1600 Scaffold 분기는 이번에 넣지 않는다.** `DECISION-FRONTEND-NAVIGATION-COMPONENT-
SLICING.md` §2가 "그 두 분기는 rail이 expanded 상태를 가질 때 같이 따라온다"고 적었고 rail
expanded는 아직 deferred다. 지금 넣으면 소비자 없는 분기가 된다.

## 6. fixture에서 실제 route로 승격되는 기준 — 새 판단

`canonical-examples`는 **레이아웃 토폴로지를 증명하는 발사대**이지 제품 route가 아니다.
승격하려면 넷을 통과한다.

1. **다섯 클래스 전부에서 측정됐는가.** pane 수·폭·순서를 재고 5절 표와 대조한다.
2. **제품 데이터가 primitive에 없는가.** 노드만 받고 Actor·Object를 모르는가.
3. **필요한 상태가 URL에서 파생되는가.** 파생되지 않으면 아직 준비가 안 된 것이고,
   컴포넌트 상태로 메우지 않는다.
4. **두 번째 소비자가 있거나 이름이 붙었는가.** 호스트 하나로 그어진 경계는 추론이다 —
   nav item을 두 호스트의 토큰 표를 나란히 놓고 나서야 제대로 가른 것과 같은 이유이고,
   이 넷 중 제일 자주 어겨질 기준이다.

## 7. 순서

```txt
1  use-window-size-class + viewport.json을 실제로 읽게 하기
2  canonical fixture를 다섯 클래스에서 측정 — 현재 CSS가 5절 표와 맞는지
3  스타일북 레이아웃 표본, 측정 readout 포함
4  (rail expanded가 올 때) 1200 / 1600 분기
```

Sheet·dialog는 이 다음이다. `surface.yml`이 presentation을 breakpoint에서 전환하는 것으로
정의하므로, breakpoint 신호가 없으면 presentation을 고를 수 없다.

## 8. 미결 — 소유자 판정

- 6절의 승격 기준 네 개를 이대로 둘 것인가.
- `useWindowSizeClass`가 `null`을 돌려주는 구간(마운트 전)에 대해, 호출자가 무엇을
  렌더해야 하는가. 지금 제안은 "CSS가 들고 있게 두고 JS는 아무 선택도 하지 않는다"이다.
- Android 예제의 `600 / 840 / 1240` 경계는 **채택하지 않는다**(예제의 구현 선택이고 현재
  Expressive 표가 아니다). m3.material.io 문서 사이트 CSS의 `600 / 960 / 1295`도 마찬가지로
  그 사이트 자신의 chrome 규칙이다. 둘 다 `viewport.json`을 바꿀 근거가 아니라는 것을
  기록해 둔다.

## 관련 기록

- `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md` — 어휘·클래스·pane 수·층 경계 (채택됨)
- `DECISION-FRONTEND-NAVIGATION-MODEL.md` — URL이 실재한다 (채택됨)
- `DECISION-FRONTEND-NAVIGATION-COMPONENT-SLICING.md` §2 §5 — 1200/1600이 rail과 함께 온다
- `SOURCE-M3-ADAPTIVE-LAYOUT-RAW.md`, `SOURCE-M3-CANONICAL-LAYOUTS-RAW.md` — 1차 자료
- `products/styleguide/_data/surface.yml` — presentation × breakpoint
