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
useWindowSizeClass() → 'compact' | 'medium' | 'expanded' | 'large' | 'extraLarge'
```

경계는 `viewport.json`에서 읽고 `matchMedia`로 구독한다. 그래야 그 JSON이 장식이 아니게
된다.

**첫 render에서 동기로 계산한다. `null` 구간을 두지 않는다.** 이 문서의 초고는 마운트 전에
`null`을 돌려주자고 적었는데 틀렸다. `null`은 서버 렌더가 답할 수 없을 때 필요한 것이고, 이
앱에는 그런 구간이 없다 — `src/apps/frontend/index.js`가 `createRoot(...).render(...)`로
클라이언트에서만 마운트하고 `hydrateRoot`는 어디에도 없으므로, 첫 render 시점에 `matchMedia`가
이미 답한다. 오히려 `null`을 두면 **CSS가 대신 들어줄 수 없는 바로 그 경우**에 한 프레임짜리
잘못된 구조가 생긴다. nav item의 orientation처럼 DOM이 갈리는 전환에서 첫 프레임이 vertical로
그려졌다가 바뀌는 식이다. 서버 렌더가 생기면 그때 다시 논의한다.

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

| 클래스 | navigation surface | Feed | List-detail | Supporting pane |
| --- | --- | --- | --- | --- |
| compact 0–599 | bottom bar | 1열 | **1 pane** — route가 list/detail 선택 | 세로 스택 |
| medium 600–839 | **route가 고른다** | 2열 | 1 pane | 세로 스택 |
| expanded 840–1199 | rail | 적응형 그리드, 카드 최소 240 | **2 pane** | 고정 **360px** |
| large 1200–1599 | rail | 〃 | 2 | 고정 **412px** |
| extra-large 1600+ | rail | 〃 | 2, `extra`가 있으면 **3** | 고정 412px |

채택된 pane 수 표와 일치한다. 현재 CSS가 이미 feed 600/840/1200/1600, list-detail 840/1600,
supporting 840을 갖고 있으므로 2단계는 **새로 쓰는 것이 아니라 재서 표와 맞는지 확인하는
일**이다.

### medium의 navigation surface는 전역 규칙이 아니다

M3는 medium에서 bar와 rail을 **둘 다 허용**하고 "가로 공간과 세로 공간 중 무엇을 우선할지"를
제품이 정하라고 한다. 그 사실은 `navigation_bar.yml`의 discrepancy에 이미 미결로 적혀 있다.
현재 `scaffold.css`가 600에서 rail로 바꾸는 것은 **baseline이지 세 fixture 전체의 제품
결정이 아니다.** 표에 `medium → rail`로 박으면 열어 둔 판정을 조용히 닫는 것이 된다.

### 정정 — supporting pane은 비율이 아니라 고정 폭이다

이 문서의 초고는 expanded를 `7:3`으로 적었다. **틀렸고, 출처도 잘못됐다.** 그 비율은 Android
샘플의 legacy View 구현이고, 채택된 기록은 다른 것을 말한다 — "Fixed pane widths are Frontend
spatial tokens: `360px` at expanded and `412px` at large and extra-large."
(`DECISION-FRONTEND-ADAPTIVE-LAYOUT.md`)

그리고 **구현은 처음부터 옳았다.** `supporting-pane.css`는 840에서
`minmax( 0, var( --ax-layout-fixed-pane-width ) )`로 고정 폭 토큰을 쓴다. 즉 이 계획서가
그대로 실행됐다면 맞는 코드를 틀리게 고치라고 시켰을 것이다. 샘플 구현의 수치를 계약처럼
옮겨 적는 것은 이 저장소가 Lab·VQA·Figma kit에서 반복해 걸러낸 오류이고, 여기서 한 번 더
났다.

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
2  임계값 일치 검증기 — 훅과 layout stylesheet가 같은 네 숫자를 쓰는지
3  Feed와 List-detail을 다섯 클래스에서 측정 — 현재 CSS가 5절 표와 맞는지
4  wp-admin의 기존 list-detail 표면을 제품 VQA로 읽기
5  첫 소비자가 필요로 하는 canonical layout을 route로 승격 — Feed가 아닐 수도 있다
6  (compact·medium의 supporting 표현이 실제로 요구될 때) Sheet
7  (rail expanded와 함께) 1200 / 1600의 chrome 판단
```

### 임계값은 두 곳에 적힌다 — 그래서 검증기가 필요하다

훅이 `viewport.json`을 읽어도 **CSS와 자동으로 한 출처가 되지는 않는다.** 채택된 기록이 그
이유를 이미 적어 뒀다 — "A custom property cannot supply a media-query condition, so those
threshold literals remain in the owning layout stylesheet." 즉 `600 / 840 / 1200 / 1600`은
구조적으로 두 벌이다.

그러므로 **둘이 같은지 비교하는 작은 검증기**가 1단계와 같이 가야 한다. 이번 세션이 보여준
것 그대로다 — 기록만 하고 강제하지 않은 수치는 드리프트하고, 심지어 아예 구현되지 않은
채로도 통과한다(rail의 64dp).

### Sheet는 Supporting pane의 전제가 아니다 — 정정

이 문서의 초고는 "Supporting pane이 양쪽 끝에서 Sheet에 묶여 있다"고 적었다. **과했다.**
원문은 compact와 medium에서 supporting pane을 primary 아래로 **reflow**하라고 하고, bottom
sheet는 "useful"한 선택지로 제시한다. 즉 지금 서 있는 세로 reflow는 **이미 유효한 canonical
topology**이고, 더 할 것이 없어서 기다리는 상태가 아니다.

Sheet가 필요해지는 것은 그 다음 **제품 판정**이다 — compact·medium에서 supporting 정보를
계속 co-planar로 둘 것인가, 초점을 보존하려고 docked·floating sheet로 바꿀 것인가.
채택된 기록의 미결("co-planar, floating, docked, reflowed, or hidden")이 바로 그 질문이고,
reflow는 그중 하나로 이미 구현돼 있다.

### 첫 승격 후보는 Feed가 아닐 수도 있다

Feed가 구조적으로 가장 싸고 List-detail은 URL·selection·Back 계약이 걸려 더 위험하다. 그러나
승격 기준은 "무엇이 쉬운가"가 아니라 **"첫 소비자가 무엇을 필요로 하는가"**다(6절). 그래서
순서가 Feed → List-detail로 고정되지 않는다. `wp-admin`에 이미 있는 list-detail 표면을 제품
VQA로 읽어 본 뒤 정하는 편이 낫다.

## 8. 미결 — 소유자 판정

- 6절의 승격 기준 네 개를 이대로 둘 것인가.
- **medium에서 어느 navigation surface를 쓸 것인가.** M3가 bar와 rail을 둘 다 허용하고
  제품이 정하라고 한 자리다. `scaffold.css`의 현재 600 전환은 baseline이고, route마다
  다를 수 있다. `navigation_bar.yml`에도 같은 미결이 적혀 있다.
- 블록 테마에 이미 list-detail 모양의 페이지가 있다. 그것이 topology의 VQA 참고가 되는지,
  아니면 React primitive와 무관한 템플릿 구성일 뿐인지. 세 층 경계상 **승격 대상은 아니지만**
  "이 토폴로지가 제품에서 어떻게 보여야 하는가"의 참고로는 쓸 수 있다.
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
