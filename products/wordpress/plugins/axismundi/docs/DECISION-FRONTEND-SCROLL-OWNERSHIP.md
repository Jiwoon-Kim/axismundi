# 결정 기록: Scroll 소유자 — Scaffold는 scroll을 소유하지 않는다

## 상태

**채택됨. 2026-10-11.** codex의 읽기·측정 감사 결과를 근거로 한 판정이고, 감사 사실은 전부
독립으로 다시 확인했다. S1은 같은 날 구현됐다. `Scaffold`는 문서 scroll owner를 유지하고,
app bar와 medium-and-up navigation rail은 그 문서에 sticky로 남는다.

이 문서가 닫아야 하는 것은 하나가 아니라 넷이다 — scroll container 소유자, `AppBar`
`scrolled`의 생산자, overlay escape 경계, inner scroll의 키보드 계약. 넷이 한 묶음인 이유는
첫 번째를 고르면 나머지 셋의 답이 달라지기 때문이다.

## 1. 결정

**`Scaffold`도 `AppLayout`도 scroll container를 소유하지 않는다. 문서가 스크롤하고, chrome은
`position: sticky`로 남는다.**

근거 네 가지.

**1-1. 문제가 둘로 분리된다.** "chrome이 스크롤에 남는가"와 "누가 scroll container인가"는
같은 질문이 아니다. `position: sticky`는 앞의 것을 풀면서 뒤의 것을 건드리지 않는다. 고정
높이 + 내부 스크롤은 두 문제를 한꺼번에 묶어서 푸는 방식이고, 묶을 이유가 아직 없다.

**1-2. 판정 불가한 것을 결정하지 않아도 된다.** 이 앱에 overlay 컴포넌트가 **하나도 없다**
(`components/`는 app-bars · buttons · cards · dividers · material · navigations). 따라서
root clipping의 안전성은 측정으로 답이 나오지 않는다. 조상에 `overflow`를 넣지 않으면
clipping context가 생기지 않고, 첫 overlay 컴포넌트가 escape 계약을 정할 때 선택지가 줄지
않는다.

**1-3. 키보드 부채가 생기지 않는다.** 문서 스크롤은 정의상 키보드로 조작된다. inner scroll은
스크롤 컨테이너에 `tabindex`와 접근 가능한 이름을 요구하고(WCAG 2.1.1), 지금 데모의 inner
scroll은 셋 다 갖고 있지 않다.

**1-4. 이미 채택된 Surface 기록이 문서 스크롤을 전제한다.** `_data/surface.yml`의 dialog
scrolling 항목은 "The page behind does not scroll with the dialog"라고 적혀 있다. lock 대상이
문서면 잘 알려진 경로이고, `__main`이면 그 기록을 다시 읽어야 한다. dialog가 아직 없는
시점에서는 **더 적은 수의 채택 기록을 깨는 쪽**을 고른다.

**잃는 것.** pane별 독립 스크롤. expanded 이상에서 list와 detail이 따로 스크롤하는 것은 M3가
보여주고 wp-admin VQA도 실측했다("collection과 detail은 각각 독립적으로 스크롤한다"). 그래서
이것은 금지가 아니라 **pane별 opt-in**이고, 전제조건이 3-3에 있다.

## 2. 기각한 대안

**A. `Scaffold { block-size: 100dvb; overflow: clip }`** — 제안자가 철회했다. 기각 이유를
남겨 두는 것은 같은 제안이 다시 올라오기 때문이다: root clipping은 **아직 존재하지 않는**
overlay들의 escape 경계를 미리 결정한다. 측정할 수 없는 것을 결정하는 모양이고, 되돌리려면
foundation을 다시 고쳐야 한다.

**B. `AppLayout`이 소유.** scroll은 window-level geometry가 아니라 route의 성격이다. 문서형
route와 feed형 route가 같은 템플릿을 쓰는데, 템플릿이 둘의 스크롤을 같게 만들 근거가 없다.

**C. route마다 소유.** 계약 없이 route마다 `overflow`를 기억해야 하는 모양. 소유자가 지적한
그대로이고, persistent chrome을 route의 기억에 맡기는 것은 계약이 아니다.

**D. 지금 결정하지 않음.** deep selector 제거 · topology 표면 · validator 셋이 전부 이 답을
기다린다. 그리고 "조상에 `overflow`를 넣지 않는다"는 **금지만으로도** 그 셋이 열린다.

## 3. 함께 확정되는 것

### 3-1. `AppBar` `scrolled`의 생산자 = `AppBar`를 만드는 route

`Scaffold`는 `appBar`를 **노드로** 받는다. 노드에는 상태를 주입할 수 없다. 그러므로 scaffold나
template이 `scrolled`를 공급하는 길은 없고, 공급하려면 render prop으로 공개 API를 바꿔야 한다.
바꾸지 않는다.

route가 window scroll을 관측해(`useScrolled()`, foundations) 자기 `AppBar`에 넘긴다.
`_data/app_bar.yml`의 "scroll observation, sticky positioning and hide/show: scroll-container
owner"와 일치한다 — 소유자가 문서이므로 관측도 window에서 한다.

모든 route가 같은 세 줄을 반복하게 되면 그때 render prop을 재고한다. 지금은 **어떤 route도
`scrolled`를 생산하지 않으므로** 반복을 관측한 적이 없다. 관측 없이 API를 넓히지 않는다.

### 3-2. overlay/portal escape = 결정하지 않는다, 대신 금지한다

판정 불가다(1-2). 결정 대신 금지를 둔다: **`Scaffold`의 조상 체인(`.ax-scaffold`,
`__content`, `__body`)은 `visible` 외의 `overflow`를 선언하지 않는다.** 첫 overlay 컴포넌트가
escape 계약을 정하고, 그 기록이 이 금지를 해제하거나 유지한다.

측정 불가를 "미결"로 두면 다음 사람이 그 자리에 무엇이든 넣는다. 금지로 두면 넣을 수 없다.

### 3-3. inner scroll의 키보드 계약 = opt-in의 전제조건

pane이 inner scroll을 쓰면 **반드시** 스크롤 컨테이너에 `tabindex="0"`, 접근 가능한 이름,
그리고 그 이름이 pane의 역할과 일치해야 한다. 전제조건이므로 첫 opt-in이 건너뛸 수 없고,
validator가 핀한다.

### 3-4. public custom-property hook의 최소 범위 = 하나

```css
.ax-scaffold__main { padding: var( --ax-scaffold-main-padding, 0 ); }
```

`--ax-scaffold-main-overflow`는 **두지 않는다.** 기본값 `auto`는 stylebook 12개 페이지를 조용히
inner scroll로 바꾸고(3-3을 전부 위반한 상태로), 기본값 `visible`은 선언할 이유가 없다. 실제로
필요해지는 시점은 첫 다중 pane route이고, 그때 **3-3의 전제조건과 함께** 추가한다.

`--ax-scaffold-block-size`도 **두지 않는다.** 데모의 높이 clamp는 hook으로 옮길 대상이 아니라
없어질 대상이다. 그 clamp는 우리가 채택하지 않는 topology(고정 높이 + 내부 스크롤)를
증명하고 있었다. topology 표면은 sticky chrome + 문서 스크롤을 보여야 한다.

### 3-5. Carousel의 비수평 전체 목록 경로 = 표준 route에서 항상 필요

M3 Carousel은 vertically scrolling page에 놓인 non-full-screen Carousel에 수평 스크롤 없이
모든 item을 볼 수 있는 경로를 요구한다. 이 문서가 Social의 기본 scroll owner를 document로
채택했으므로 표준 Social route는 그 조건을 항상 만족한다. 따라서 header의 48dp arrow action
또는 Carousel 아래의 padding 4dp Show all button은 선택적 enhancement가 아니라 필수 조합이다.
full-screen Carousel만 예외다.

구체적인 Home 조합과 구현 순서는 `PLAN-FRONTEND-HOME.md`가 소유한다. 이 문서는 조건이 항상
참이라는 scroll 측의 사실만 소유한다.

### 3-6. inner scroll opt-in은 모달 scroll lock 대상도 같이 옮긴다 (결과)

결정이 아니라 **이미 출하된 코드에 대한 측정된 사실**이다. 5절의 판정이 어느 쪽으로 나든
이 사실은 그대로이므로, 판정과 함께 읽히도록 여기 적는다.

`styles/components/surface.css`의 모달 lock은 문서를 잠근다.

```css
html:has( .ax-surface:modal ) { overflow: hidden; }
```

그 선택자는 1절이 문서를 scroll owner로 만들었기 때문에 `html`이고, 그 파일의 주석이
그렇게 적고 있다. **루트를 잠가도 안쪽 scroll container는 잠기지 않는다.** 따라서 pane이
inner scroll을 켜는 순간 이 lock은 그 pane에 대해 아무 일도 하지 않는다 — 모달이 떠 있고
뒤의 pane이 계속 스크롤된다. `showModal()`의 inert는 상호작용에 관한 것이지 뷰포트
스크롤에 관한 것이 아니라는 측정이 그 파일에 이미 적혀 있고, 같은 이유로 여기에도 적용된다.

증상이 조용하다는 점이 비용이다. 레이아웃은 멀쩡해 보이고, 콘솔에 아무것도 남지 않으며,
`surface.yml`의 "The page behind does not scroll with the dialog"는 계속 통과하는 것처럼
보인다. 발견되는 자리는 피드를 다 만든 다음이다.

그리고 scroll owner가 breakpoint마다 다르면 — compact는 pane이 하나라 문서, expanded는
둘이라 pane — **lock 대상도 breakpoint마다 달라진다.** 5절의 opt-in을 켜는 작업은 이
lock을 같이 옮기는 작업을 포함한다. 3-3과 3-4가 전제조건인 것과 같은 의미에서 전제조건이다.

## 4. 검증 기준

CSS 핀 — 가장 값싸고 가장 안 깨진다.

- `.ax-scaffold`, `__content`, `__body`에 `overflow` 선언 **0건**.
- `grid-template-areas`에 `"rail content"` 존재.
- stylebook/앱 CSS에 `.ax-scaffold__content >` 경로 **0건**.
- `.ax-scaffold__main`이 `--ax-scaffold-main-padding`을 읽는다.

JSX 핀 — 각 마커가 **정확히 1회** 등장함을 먼저 단정하고, 그 다음에 순서를 비교한다. 마커가
사라졌을 때 조용히 통과하는 모양이면 핀이 아니다.

- `__rail`이 `__content`보다 앞.
- `__app-bar`와 `__body`가 `__content` 안.
- `__main`과 `__supporting`이 `__body` 안.

측정 핀 — topology 표면의 readout이 매번 출력한다.

- `rail.right == content.left == appBar.left`.
- 스크롤 후에도 app bar와 rail의 viewport y가 같다.
- `document.scrollingElement`가 **유일한** scroll 컨테이너다.
- inner scroll을 쓰는 pane이 생기면 `tabindex`와 접근 가능한 이름이 있다.

## 5. 미결 (소유자 판정)

- **독립 pane 스크롤을 언제 켜는가.** 첫 다중 pane route가 생길 때이고, 그 route가 3-3을
  충족해야 한다. 지금 다중 pane은 데모에만 있다.
- **sticky chrome의 구현.** 닫힘. app bar와 medium-and-up rail 둘 다 sticky이고, 기준선은
  `0`이 아니라 `--ax-scaffold-safe-area-block-start`다 — S2가 safety region을 Scaffold의
  것으로 만들었으므로 chrome은 그 아래에 붙는다. rail은 `100dvb`가 아니라
  `calc( 100dvb - safe-start - safe-end )`로 **두 safety region 사이에** 들어간다. 처음에는
  `100dvb`로 구현됐고, 두 custom property를 notch 기기 값(44/34)으로 강제해서 재니 rail
  하단이 뷰포트를 44px 넘어갔다. 둘 다 document scroll을 읽으며 inner scroll을 만들지 않는다.
  topology 표면이 document scroll과 두 chrome의 viewport 좌표를 함께 출력한다. rail이
  viewport보다 긴 경우의 탐색은 첫 실제 route의 navigation content가 생길 때 다시 측정한다.

## 관련 기록

- `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md` — window size class와 pane 어휘. VQA 2번이
  "overflow, and keyboard focus order"를 항목으로 들고 있었고, 이 문서가 그 자리를 채운다.
- `PLAN-FRONTEND-CANONICAL-LAYOUTS.md` — Feed/List-detail/Supporting의 현재 위치. 이 기록이
  채택되면 그 절은 canonical primitive 증명이 아니라 조합 smoke test로 낮춰 적어야 한다.
- `_data/surface.yml` — dialog scrolling(1-4), sheet scroll 방향.
- `_data/app_bar.yml` — on-scroll 토큰과 "scroll-container owner" 위임(3-1).
- `_data/carousel.yml`과 `PLAN-FRONTEND-HOME.md` — document scroll 때문에 항상 활성화되는
  Show all 경로(3-5).
- `DECISION-FRONTEND-LAYOUT-NAMING.md` — 같은 턴의 명명 정렬. 이름이 소유를
  가리키므로 함께 읽는다.
