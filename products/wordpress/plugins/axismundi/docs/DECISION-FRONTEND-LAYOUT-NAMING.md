# 결정 기록: 레이아웃 명명 정렬 — Compose Material 3 Adaptive

## 상태

**제안됨. 2026-10-06.** `DECISION-FRONTEND-SCROLL-OWNERSHIP.md`와 **함께 읽어야 한다.**
이름이 소유를 가리키고 있지 않으면 그 문서의 계약이 읽히지 않는다. 별도 파일인 이유는
그 문서의 제목이 scroll 소유자이고, 명명은 같은 턴에 결정되지만 같은 주장이 아니기 때문이다.

출처 우선순위: **Compose Material 3 Adaptive의 공개 API > Material 웹 > Angular Material**.
Angular는 "side navigation과 content가 형제 컨테이너"라는 웹 선례로만 쓴다. canonical pane
명칭의 출처가 아니다 — `mat-drawer-container`/`mat-drawer`/`mat-drawer-content`뿐이고
`NavigationRail`도 pane scaffold도 발행하지 않는다.

원칙 하나로 줄이면: **공식 이름이 있는 자리에는 공식 이름을 쓰고, 없는 자리에 공식처럼
보이는 이름을 만들지 않는다.** 뒤쪽 절반이 이 문서 분량의 대부분이다.

## 1. `Scaffold`의 이름 — prop을 접기 전에는 바꾸지 않는다 (금지)

`Scaffold`를 `NavigationSuiteScaffold`로 **지금 바꾸지 않는다.** 계약이 다르다.

```txt
Compose  destination을 한 번 받고, 컴포넌트가 bar/rail을 고른다
우리     bar와 rail을 두 노드로 받고, CSS 미디어쿼리가 고른다
```

그리고 더 직접적인 이유가 있다. 제품 route는 `<Scaffold>` 를 **prop 없이** 호출한다 —
app bar도 navigation bar도 rail도 없다. **지금 제품에는 navigation suite가 존재하지 않는다.**
bar와 rail은 stylebook에만 있다. navigation suite가 없는 컨테이너를 navigation suite라고
부르는 이름이 된다.

**금지:** prop이 접히기 전에 rename하지 않는다. 이름이 계약을 거짓 진술한다.

**접기의 근거는 추측이 아니다.** `canonical-layout-fixtures.js`가 **같은** `destinations`와
**같은** `activeId`를 `NavigationBar`와 `NavigationRail`에 중복으로 넘긴다. 둘을 함께 쓰는
유일한 자리에서 이미 중복이 관측됐다. 접었을 때의 모양:

```js
Scaffold({ navigation: { destinations, activeId, label }, appBar, supporting, children })
```

bar/rail 전환은 **CSS에 남긴다.** `useWindowSizeClass`는 DOM 자체가 달라지는 결정에만 쓴다는
기록이 이미 있고, 이것은 그 경우가 아니다.

접기가 끝나면 rename은 그 커밋에 딸려 온다 — 그때는 이름이 참이 된다.

**migration 범위(현재):** `Scaffold`는 JS 15개 파일, 우리 문서 5개에 나온다. 가장 비싼
rename이고, 그래서 계약이 바뀌기 전에 하지 않는 것이 더욱 맞다.

## 2. `AppLayout` — 제거 완료, 재도입 기준

제거했다. **이유가 중요하다: shell context가 하나여서가 아니다.** shell context는 이미 둘이다 —
`FrontendApp`이 stylebook을 `AppLayout` **밖에서** 렌더하고 stylebook은 자기 `Scaffold`를
직접 만든다. 선택은 처음부터 `FrontendApp`의 분기에 있었고, `AppLayout`은 그 선택을 하지 않는
pass-through였다.

이 구분이 없으면 재도입 기준이 오늘 이미 충족돼서 즉시 재도입을 지시한다.

**재도입 기준: 같은 chrome 조합이 두 route 이상에서 반복되는 것을 관측할 때.** 개수가 아니라
반복이다. 그때도 상위 계층은 물리적 grid를 다시 구현하지 않고 **선택만** 소유한다.

이것은 scroll 문서 3-1(`scrolled` 생산자를 route에 둔 것)과 같은 규율이다 — 반복을
관측하기 전에 API를 넓히지 않는다.

## 3. `ListDetailLayout` → `ListDetailPaneScaffold`

채택한다. 슬롯은 **이미 공식과 같다** — `list` / `detail` / `extra`. 바뀌는 것은 컴포넌트
이름뿐이다.

**migration:** JS 2개 파일(정의, fixture), 우리 문서 3개. 공개 CSS 클래스
`ax-list-detail-layout*`는 **유지**한다(6절).

## 4. `SupportingPaneLayout` → `SupportingPaneScaffold`

채택한다. 그리고 이것은 rename이 아니라 **누락 발견**이다.

```txt
현재    primary / supporting
공식    mainPane / supportingPane / extraPane
```

`primary → main`은 한 글자지만, 정렬하면 **`extra`가 아예 없다는 사실**이 드러난다. 소유자가
"generic primary/secondary로 뭉개지지 않아야 한다"고 지목한 자리가 정확히 여기다.

`extra`를 지금 구현할지는 별 문제다 — list-detail은 840/1600에서 2/3 pane을 이미 갖고 있고
supporting은 840 reflow만 갖고 있다. **이름은 정렬하고, 세 번째 pane의 구현은 제품 요구가
생길 때** 한다. 빠져 있다는 사실이 기록되는 것이 이 절의 산출물이다.

**migration:** JS 2개 파일, 우리 문서 2개. 호출부의 `primary` → `main`.

## 5. `compactPane` — `NON-STANDARD`로 유지

공식에 이 prop은 없다. Compose는 `ThreePaneScaffoldNavigator`와 `scaffoldDirective`로 푼다.
우리는 **navigator를 채택하지 않고 선택을 호출자에게 뒀다** — 이미 채택된 모양이고,
`ListDetailLayout`의 주석이 "Selection and navigation remain outside this layout"이라고 적고
있다.

그러므로 공식 이름을 쓸 수 없는 자리다. 이 저장소 관례대로 **`NON-STANDARD` 주석에 사유를
적고 유지**한다. 지우지 않고, 공식 이름을 덮어쓰지 않는다. navigator를 채택하게 되면 그때
이 자리가 바뀐다.

## 6. CSS 티어 경계 — 바꾸지 않는다

### 6-1. 클래스는 유지하고, 불일치는 의도된 것으로 기록한다

`ax-` 는 CSS 충돌 회피 namespace로만 남는다. **공개 CSS 클래스를 이 작업에서 개명하지
않는다.** React 이름이 `ListDetailPaneScaffold`인데 클래스가 `ax-list-detail-layout__list`로
남는 것은 **의도된 불일치**다.

적어 두는 이유: 적어 두지 않으면 다음 사람이 "정합성"으로 클래스를 맞춘다. 그리고 그
작업이 deep selector 제거와 같은 턴에 겹치면 사고 지점이 둘로 늘어난다.

### 6-2. `md-*` 는 발행된 것에만 쓴다

이 경계는 **이미 기록돼 있다.** `styleguide/assets/css/tokens.sys.layout.css` 머리말:

> `--md-sys-*` is M3's: the breakpoints and the 360dp supporting pane.
> `--ax-sys-*` is this project's: column counts, the margin and gap around and between them…

그리고 그 파일에 실제로 있는 `--md-sys-layout-*`는 breakpoint 다섯 개와 `pane-supporting`
하나뿐이다. 발행된 것만 있다.

**기각한 제안:**

- `--md-sys-layout-pane-gap`, `--md-sys-layout-pane-primary-width` — M3가 발행하지 않은
  수치다. 이 저장소가 **이미 `--ax-*`로 분류한** 값들이고, 그 이유가 위 머리말에 적혀 있다.
- `--md-comp-navigation-suite-*`, `--md-comp-list-detail-pane-scaffold-*` — M3에 그 컴포넌트
  토큰 표가 없다. 이번 작업에서 `md.comp.navigation-bar`(deprecated, 80dp)와
  `md.comp.nav-bar`(live, 64dp)를 구분하지 못해 슬롯 이름을 전부 고친 사고가 같은 형이다.
- `--md-ref-layout-*` — `md.ref.*`는 palette와 typeface 티어다.
- `.ax-pane--compact` — 현재는 `data-compact-pane="list|detail"`로 **어느 pane인지**를 담는다.
  boolean modifier는 그 값을 표현할 수 없다.

발행되지 않은 수치에 `md-` 접두사를 붙이는 것은 거짓 진술이다. `ax-`는 우리 것이라고 말하고,
`md-comp-`는 M3가 발행했다고 말한다. **우리 것으로 두는 쪽이 더 정직하고, 더 싸다.**

## 7. 검증 기준

- `Scaffold`의 공개 이름이 `NavigationSuiteScaffold`라면, `navigationBar`/`navigationRail`
  두 prop이 **동시에** 존재하지 않는다. 둘 다 있으면 rename이 계약을 앞질렀다.
- `destinations`와 `activeId`를 두 컴포넌트에 각각 넘기는 호출부가 0건.
- `AppLayout`이라는 이름이 JS에도 CSS 클래스에도 없다. (유령 클래스 사고가 한 번 있었다.)
- `SupportingPaneScaffold`의 슬롯이 `main`/`supporting`이고, `primary`를 받는 호출부가 0건.
- `compactPane` 주변에 `NON-STANDARD` 주석과 사유가 있다.
- `md-sys-layout-*`와 `md-comp-*` 중 발행 표에 없는 이름이 0건.
- `ax-list-detail-layout*`, `ax-supporting-pane-layout*` 클래스가 그대로 있다.

## 8. 미결 (소유자)

- **prop 접기를 언제 하는가.** 지금은 stylebook만 bar/rail을 함께 쓴다. 제품 route가
  navigation을 갖게 되는 시점과 묶는 것이 자연스럽지만, 그 전에 해도 손해는 없다.
- **`extra` pane의 구현 시점.** 이름만 정렬하고 미루는 것이 이 문서의 입장이다.

## 관련 기록

- `DECISION-FRONTEND-SCROLL-OWNERSHIP.md` — 같은 턴의 소유 계약. 함께 읽는다.
- `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md` — Material 어휘와 window size class.
- `PLAN-FRONTEND-CANONICAL-LAYOUTS.md` — 공개 API 표와 `AppLayout` 제거 기록.
- `SOURCE-M3-ADAPTIVE-LAYOUT-RAW.md`, `REFERENCE-M3-ADAPTIVE-LAYOUT-SOURCE.md` — **원문
  보존 파일이다. 이름이 바뀌어도 고치지 않는다.** 이 문서의 migration 범위에 들어가는 것은
  우리가 쓴 문서뿐이다.
