# 결정 기록: overlay의 escape 경계와 standard sheet의 push 대상

## 상태

**채택됨. 2026-10-11.** 3·4·8절은 소유자 판정이고, 같은 날 승인됐다. 2절은 측정이고,
5~7절은 그 측정과 판정에서 따라 나온다.

3절은 "유지냐 해제냐"가 아니라 **근거를 교체하고 유지하는 것**이 승인된 것이다. 2절의 측정이
overlay 쪽 근거를 없앴고, 남은 근거는 scroll owner 하나다. 핀은 같고 문장이 다르다.

측정 둘 중 하나가 예상을 반증했다(6절). 그 결과 initial focus는 "코드의 부재"가 아니라 명시
구현이 됐고, 7절의 결론은 그대로이되 근거가 바뀌었다. 기록을 고쳐 적은 자리를 지운 것이
아니라 남겨 두었다 — 같은 추론이 다시 올라오기 때문이다.

`DECISION-FRONTEND-SCROLL-OWNERSHIP.md` 3-2절은 overlay escape를 **결정하지 않고 금지**해
두었고, 그 금지에 조건을 달았다 — "첫 overlay 컴포넌트가 escape 계약을 정하고, 그 기록이 이
금지를 해제하거나 유지한다". `DECISION-FRONTEND-OVERLAY-SURFACE-OWNERSHIP.md` 6절은 그
"첫 overlay"가 아직 만들어지지 않았다고 적었다. 이 문서가 그 자리를 채운다.

## 1. 질문은 하나가 아니라 다섯이다

```txt
1  모달 surface는 조상의 overflow를 탈출하는가          → 측정 (2절)
2  3-2절의 금지를 해제하는가                            → 결정 (3절)
3  standard side sheet가 미는 것은 무엇인가              → 결정 (4절)
4  compact에서 standard가 modal이 될 때 DOM이 움직이는가 → 결정 (5절)
5  M3의 세 컴포넌트를 몇 개로 받는가                     → 결정 (8절)
```

앞의 넷이 한 묶음인 이유는 1번의 답이 2번을 좁히고, 2번의 답이 3번의 선택지를 정하기
때문이다. 5번은 소유자가 따로 물은 것이고, 1~4번의 답이 공유 코어의 범위를 정하므로 뒤에 둔다.

## 2. 측정 — top layer와 grid 자녀는 서로 다른 문제다

native `<dialog>`를 `showModal()`로 열면 그 박스는 **top layer**에 렌더된다. top layer는
문서의 일반 흐름 밖이고, 조상의 `overflow`·`clip-path`·`transform`·`filter`가 만드는
clipping context와 stacking context에 속하지 않는다.

> **측정 완료. 2026-10-11, Chrome 152, 스타일북 surfaces 페이지.**
>
> `.ax-scaffold`·`__content`·`__body` 셋에 `overflow: clip !important`를 주입해도 열린
> 모달의 `getBoundingClientRect()`가 소수점까지 동일했다(x 228.73959350585938, 551x208).
>
> `identical: true` 하나는 "주입한 스타일이 아무 일도 안 했다"로도 설명되므로 **양성
> 대조**를 함께 뒀다. 같은 조상 체인 안에 in-flow 상자를 하나 넣고 클리핑 조상의 높이를
> 48px로 줄인 뒤 스크린샷을 찍었다. y 220~260의 대조 상자는 사라졌고, y 280~488의 모달은
> 스크림까지 온전히 그려졌다. 클립이 실재했고 모달만 빠져나간다.
>
> 측정 중 `elementFromPoint`는 쓸 수 없었다. 모달의 `::backdrop`이 뷰포트를 덮으므로 어느
> 좌표를 찍어도 dialog가 반환된다. 페인트를 재려면 스크린샷이어야 한다.

반대쪽은 측정이 필요하지 않다. **standard sheet는 top layer에 들어가지 않는다** —
`show()`는 비모달이고 비모달 dialog는 일반 흐름에 남는다. 그래서 standard sheet는
탈출하지 않고, 탈출할 필요도 없다. 그것은 페이지를 가리는 레이어가 아니라 페이지와
공간을 나누는 영역이기 때문이다(`surface.yml` `side_sheet.standard`: "Opening it shrinks
the page's body by its width").

즉 **escape는 modality의 함수이고 presentation의 함수가 아니다.**

```txt
modal     top layer       조상의 overflow와 무관
standard  일반 흐름        조상의 grid가 자리를 준다
```

## 3. 결정 — 3-2절의 금지는 유지한다. 근거를 교체한다

소유자 판정이 필요하다.

3-2절의 금지는 **그대로 남긴다.**

```txt
.ax-scaffold, .ax-scaffold__content, .ax-scaffold__body
  visible 외의 overflow를 선언하지 않는다
```

그러나 **금지의 근거가 바뀐다.** 원래 근거는 "overlay가 없어서 escape의 안전성을 측정할 수
없다"(3-2절, 1-2절)였다. 2절의 측정이 그 근거를 해소한다 — 모달은 조상의 `overflow`와
무관하므로, 조상에 `overflow`가 있어도 모달 overlay는 깨지지 않는다. overlay를 이유로 금지할
필요가 없어졌다.

남는 근거는 **scroll owner**다. 3-1절은 문서가 scroll container이고 chrome이 `sticky`로
남는다고 정했다. 조상에 `overflow`를 선언하면 그 조상이 scroll container가 되고 `sticky`의
containing block이 바뀐다. 그것은 overlay 문제가 아니라 1절이 이미 결정한 문제다.

**이 구분이 중요한 이유.** 금지를 "overlay 때문"이라고 적어 두면, 다음 사람이 모달의
escape를 확인하고 금지를 해제할 근거로 쓴다. 그러면 문서 스크롤이 조용히 깨진다. 같은 금지가
남더라도 **무엇이 그것을 요구하는지**는 고쳐 적어야 한다.

```txt
해소됨  overlay escape      모달은 top layer로 탈출한다 (2절)
유지됨  scroll container    문서가 소유한다 (3-1절)
```

### 3-1. 따라서 portal을 두지 않는다

React 쪽 Surface는 `createPortal`을 쓰지 않는다. top layer 배치는 **DOM 위치와 무관**하므로,
모달은 선언된 자리에서 그냥 열린다. portal은 조상 clipping을 피하려고 쓰는 도구이고, 피할
clipping이 없다.

portal을 안 쓰는 것이 이득인 이유는 셋이다 — context가 끊기지 않고, 이벤트 버블링이
자연스럽고, standard sheet가 **같은 컴포넌트로** 흐름 안에 남을 수 있다(4절).

## 4. 결정 — standard side sheet가 미는 것은 Scaffold의 grid column이다

소유자 판정이 필요하다. 층 경계를 움직인다.

블록 쪽 구현은 `.wp-site-blocks`에 sheet 폭만큼 padding을 주입한다(`surface.yml`
`windows.page_share.resize`). 블록에는 Scaffold가 없으므로 그것이 유일한 방법이었다.

React 쪽은 Scaffold가 있다. **standard sheet는 `.ax-scaffold__body`의 grid column으로
렌더되고, 페이지가 좁아지는 것은 주입이 아니라 grid의 결과다.**

```txt
블록      <html>에 padding 주입          런타임이 폭을 재서 CSS 변수로 쓴다
React     __body의 grid-template-columns  열이 생기면 main이 minmax(0,1fr)로 줄어든다
```

주입보다 나은 이유 셋.

**4-1. 측정이 사라진다.** 주입식은 sheet의 실제 폭을 재서 조상에 써야 하고, 폭은 토큰·창
크기·사용자 설정에 따라 바뀐다. grid는 폭을 재지 않는다 — 열의 `auto`가 그 자리를 안다.

**4-2. `minmax(0, 1fr)`이 이미 있다.** `scaffold.css`의 `__body`는 이미
`grid-template-columns: minmax( 0, 1fr )`이고 expanded에서 `supporting` 열을 붙인다. sheet
열은 같은 자리의 같은 모양이다. 새 메커니즘이 아니라 이미 선 메커니즘의 두 번째 사용자다.

**4-3. supporting pane과 충돌하지 않는다.** 둘은 같은 열을 차지할 수 있지만 같은 것이
아니다 — supporting pane은 canonical layout의 일부라 항상 있고, standard side sheet는 열고
닫힌다. 그래서 **별도 slot**으로 둔다. 동시에 열렸을 때 무엇이 먼저인지는 11절의 미결이다.

### 4-1. `Scaffold`는 `sheet` slot을 받는다

`appBar`·`supporting`과 같은 모양 — 노드를 받는 prop 하나.

```jsx
<Scaffold appBar={ … } navigationRail={ … } sheet={ <Sheet … /> }>
```

`supporting`이 노드 prop인 것과 같은 이유로 노드다(3-1절, scroll 기록: "노드에는 상태를
주입할 수 없다"). sheet의 열림 상태는 sheet를 만드는 route가 소유하고, Scaffold는 자리만
준다.

### 4-2. `move`는 채택하지 않는다

`surface.yml` `windows.page_share.values`는 `[resize, move]`이고 `move`는 "A project
option"으로 적혀 있다. React 쪽은 `resize`만 구현한다. `move`는 Scaffold 전체를
`translate`해야 하고, 그러면 `sticky` chrome이 함께 밀려서 3-1절이 정한 chrome persistence가
깨진다. 보류가 아니라 **이 토폴로지에서 비채택**이고, 이유를 기록에 남긴다.

## 5. 결정 — modality가 바뀔 때 DOM은 움직이지 않는다

`surface.yml` `adaptive`는 compact에서 standard side sheet가 modal이 된다고 적었다. 구현상
문제는 "그러면 sheet가 grid column에서 top layer로 옮겨 가는가"다.

**옮기지 않는다.** top layer 배치는 DOM 위치와 무관하므로(3-1절), grid slot에 선언된 같은
`<dialog>`를 `showModal()`로 열면 그것이 top layer에 간다. 열 자리는 비어 있게 되고
(`dialog:not([open])`은 `display: none`, 열려 있어도 top layer에 있으면 흐름을 차지하지
않는다), 같은 엘리먼트가 모달로 보인다.

```txt
expanded / medium   slot에서 show()      흐름에 남아 열을 차지한다
compact             slot에서 showModal() top layer로 올라간다
```

**바뀌는 것은 여는 방법 하나뿐이다.** 상태도, 트리도, ref도 그대로다. 이것이 4절에서 slot을
고른 두 번째 이득이다 — 주입식이라면 compact에서 주입을 되돌리고 모달로 다시 마운트해야 한다.

## 6. 결정 — initial focus는 명시 구현한다 (측정이 예상을 반증했다)

`surface.yml` `conflicts`의 `initial focus`는 2026-10-11에 판정됐다: React 표면은 M3 접근성
페이지를 따라 **첫 상호작용 요소**에 포커스한다.

**이 절은 원래 "그 구현은 아무것도 쓰지 않는 것"이라고 적혀 있었다.** HTML의 dialog focusing
steps가 이미 그 순서이므로(`autofocus` → 첫 focusable 자손 → dialog 자신), 블록이
`tabindex="-1"` + 명시 포커스를 쓴 것은 그 기본값을 **뒤집기 위한** 코드이고, 우리는 뒤집지
않으니 코드가 없다는 추론이었다. 규범을 읽은 추론이었고, 틀렸다.

> **측정. 2026-10-11, Chrome 152.**
>
> body가 넘치지 않는 dialog는 예상대로 첫 버튼(Cancel)에 포커스했다. **body가 넘치는 순간
> 포커스가 스크롤 영역으로 간다.** 그 영역은 `tabIndex === -1`이고, `tabindex` 속성이 없고,
> 어떤 focusable 셀렉터에도 맞지 않는다 — 즉 sequential focus order에 없는데도 포커스를
> 받는다. 표본 둘 다에서 재현됐고, 그중 하나는 body 안에 링크가 있어 "첫 focusable 자손"
> 규칙으로는 링크가 이겨야 하는 경우였다.
>
> Chrome 127+의 keyboard-focusable scroll containers가 그리는 거동으로 보인다. 원인이
> 무엇이든 결론은 같다: **플랫폼의 기본값이 M3의 규칙과 다르다.**

그러므로 규칙을 물려받지 않고 **구현한다**. `showModal()` 직후 `applyInitialFocus()`가 첫
상호작용 요소를 찾아 포커스한다. 스크롤 영역은 `data-surface-scroll`로 표시되어 건너뛴다 —
Tab으로 닿을 수 있는 것과 포커스가 시작하는 자리는 다른 질문이고, M3는 두 번째 질문에만
답한다. 상호작용 요소가 하나도 없으면 플랫폼의 선택을 그대로 둔다.

작성자가 `autofocus`를 자손에 두면 그것이 이긴다 — 플랫폼이 이미 존중한 뒤에 이 함수가 돌고,
`autofocus`가 있으면 손대지 않는다. 파괴적 첫 동작은 APG의 예외이고, 호출자가 `autofocus`로
표현한다. 컴포넌트가 추론하지 않는다.

**남길 교훈.** 이 절의 첫 판은 "구현은 코드의 부재다"라는 문장이 깔끔해서 설득력이 있었다.
깔끔함은 측정이 아니다. 10절의 측정 핀이 이 절을 먼저 통과해야 했던 이유가 그것이다.

## 7. 결정 — 스크롤하는 body의 `tabindex`는 조건부다

M3 dialog의 scrolling 규칙은 "headline은 위에, actions는 아래에 고정"이다. 그러므로 스크롤
하는 것은 dialog가 아니라 **그 사이의 body**이고, body는 `<div>`다.

`DECISION-FRONTEND-SCROLL-OWNERSHIP.md` 3-3절은 inner scroll에 `tabindex="0"`과 접근 가능한
이름을 요구한다. 그 절의 주어는 pane이고 dialog body는 pane이 아니지만, 근거(WCAG 2.1.1)는
같다 — 키보드로 닿을 수 없는 스크롤 영역은 키보드로 스크롤할 수 없다.

조건부로 붙인다.

```txt
붙인다      body가 실제로 넘치고(scrollHeight > clientHeight)
            body 안에 focusable이 하나도 없을 때
붙이지 않는다 그 외 전부
언제        showModal() 이후. 네이티브 포커스 단계가 이미 끝난 뒤다
```

**이 두 조건은 Chrome이 scroll container를 focusable로 만들 때 쓰는 조건과 같다.** 의도한
것이다 — 그 기능이 없는 엔진에서 같은 거동을 주려는 것이고, Chrome에서는 중복이지 충돌이
아니다. Chrome이 추가로 하는 것(열 때 그 영역에 포커스를 주는 것)은 6절이 되돌린다.

초안에서는 이 조건부의 근거를 "무조건 붙이면 body가 첫 focusable 자손이 되어 6절이 깨진다"로
적었다. 6절의 측정이 그 근거를 무너뜨렸다 — 포커스는 `tabindex`가 없어도 body로 갔으므로,
`tabindex`가 원인이 아니었다. 조건은 같고 **이유가 바뀌었다.**

둘째 조건이 필요한 이유는, body 안에 focusable이 있으면 포커스가 그 안에 머물고 가장 가까운
스크롤 조상이 키보드 스크롤을 받기 때문이다. 그때 `tabindex`는 아무 접근성도 더하지 않고
탭 스톱만 하나 늘린다.

붙일 때는 `role="group"`과 `aria-labelledby`(headline)를 함께 붙인다. 이름 없는 focusable
`<div>`는 스크린리더가 읽을 것이 없고, 3-3절이 요구하는 것도 이름까지다.

## 8. 결정 — 네 행을 세 컴포넌트로 받는다

소유자 질문이다. M3는 Dialog·Bottom sheet·Side sheet를 별개 컴포넌트로 발행하는데, 블록 쪽은
`axismundi/dialog` 하나와 블록 변형으로 통합했다. React 쪽은 어느 쪽인가.

**논리는 합치고 물리는 나눈다.** 비시각 코어 하나 + presentation 컴포넌트 셋이다.

### 8-1. 블록이 통합한 이유가 React에 없다

`_components/surface.md` 결정 1은 통합 이유를 둘로 적었다.

```txt
1  크기·스크림·닫힘·포커스 설정이 두 번 구현된다
2  둘 사이의 변환이 속성을 옮겨 적는 일이 된다
```

**1번은 컴포넌트를 합치지 않고 풀린다.** 비시각 코어 모듈 하나가 `<dialog>` 호스트,
modality, open/close, motion, focus, scrim, scroll 계약을 전부 소유하면 중복이 생기지 않는다.
Tabs가 이미 그 모양이다 — `Tabs` 상태 루트 하나에 `PrimaryTabBar`와 `SecondaryTabBar`가
형제로 붙고, 토큰 표가 다르다고 상태를 두 번 구현하지 않았다.

**2번은 React에 대응물이 없다.** 블록 변환이 필요한 이유는 저장된 콘텐츠와 사이트 편집기다.
사용자가 이미 만든 파트를 잃지 않고 dialog와 sheet를 바꿀 수 있어야 하므로 블록 이름이
하나여야 했다. React에는 저장된 콘텐츠가 없고 변환할 사용자 표면도 없다. 호출자가
`<Dialog>` 대신 `<SideSheet>`를 쓰는 것이 그 변환이다.

즉 통합은 **두 컴포넌트가 같은 것이라는 판단이 아니라 저장 포맷의 요구**였다. 그 요구가
없는 런타임에 결론만 옮기면 근거 없이 anatomy 넷을 한 prop 묶음에 넣는다.

### 8-2. 2:1로 묶이는 자리는 강제성으로 정한다

`surface.yml` `adaptive`의 두 전환은 **강제성이 다르다.**

```txt
dialog-full-screen -> dialog-basic   "becomes ... from: medium"        의무
sheet-bottom       -> sheet-side     "Can be swapped ... not must"     선택
```

의무인 쪽은 한 인스턴스가 breakpoint에 따라 두 행이어야 하므로 쪼갤 수 없다. 블록의 2번
논리가 실제로 적용되는 유일한 자리이고, 그래서 `Dialog`가 `presentation` prop을 받는다.

선택인 쪽은 컴포넌트의 의무가 아니라 호출자의 조합이다. route가 breakpoint를 보고
`<BottomSheet>`와 `<SideSheet>` 중 하나를 렌더한다. 묶지 않는다.

```txt
Dialog        dialog-basic + dialog-full-screen    presentation: basic | full-screen
BottomSheet   sheet-bottom
SideSheet     sheet-side                           modality: modal | standard (4절)
```

`axes.presentation`의 네 행은 `surface.yml`에 그대로 남는다. 블록은 실제로 네 행을 한
호스트로 쓰고, 발행된 축이기도 하다. React가 그것을 셋으로 받는다는 **매핑**을 기록하되,
수치는 한 곳에 남긴다.

### 8-3. 파일

```txt
components/surfaces/surface.js        비시각 코어. 세 컴포넌트가 모두 읽는다
components/surfaces/dialog.js         basic + full-screen
components/surfaces/bottom-sheet.js
components/surfaces/side-sheet.js
styles/components/surface.css         host · scrim · motion slot
styles/components/dialog.css
styles/components/bottom-sheet.css
styles/components/side-sheet.css
```

`buttons/`가 `button.js`·`icon-button.js`·`split-button.js`를 한 디렉터리에 두고 토큰 tier를
공유하는 것과 같은 모양이다. 가족은 하나, 파일은 anatomy마다.

## 9. 기각한 대안

**A. `createPortal`로 `<body>` 끝에 보낸다.** 조상 clipping을 피하려는 도구이고, 2절에 따라
피할 clipping이 없다. standard sheet를 흐름에 남길 수 없게 되는 비용만 남는다.

**B. 3-2절의 금지를 해제한다.** 2절이 overlay 측 근거를 해소하므로 해제가 가능해 보인다.
해제하지 않는 이유는 금지를 요구하는 두 번째 근거가 남아 있기 때문이다(3절). 근거가 둘인
금지를 근거 하나가 해소됐다고 푸는 것은, 다음 사람이 같은 실수를 하도록 기록을 바꿔 두는
일이다.

**C. standard sheet도 모달로 통일한다.** 구현이 하나로 줄지만 `surface.yml`
`side_sheet.standard`의 "both stay usable"과 `axes.modality`의 "Standard means no scrim and
the page stays usable"을 버린다. 발행된 행을 구현 편의로 지우는 모양이다.

**D. sheet 열을 `supporting` slot에 합친다.** 열이 하나 줄지만 lifecycle이 섞인다 —
supporting pane은 닫히지 않고 close affordance가 없으며, side sheet는 닫기 수단이 **항상**
있어야 한다(`surface.yml` `conflicts`의 `side sheet close button`). 같은 열에 사는 것은
같은 것이라는 근거가 아니다.

## 10. 검증 기준

측정 핀 — 이 문서가 채택되기 전에 돌아야 했던 것 둘. **2026-10-11 실행, Chrome 152.**

- 통과. `.ax-scaffold`·`__content`·`__body`에 `overflow: clip`을 주입한 상태에서 열린
  모달의 `getBoundingClientRect()`가 소수점까지 같다. 양성 대조로 같은 조상 안의 in-flow
  상자가 사라지는 것을 스크린샷으로 확인했다(2절).
- **실패 후 수정.** `showModal()` 직후 `document.activeElement`가 body가 넘칠 때 스크롤
  영역이었다. 6절을 명시 구현으로 고친 뒤 세 표본(짧은 body, 긴 body, 링크를 가진 긴
  body)에서 모두 첫 상호작용 요소가 됐다.

CSS 핀.

- `.ax-scaffold`, `__content`, `__body`에 `overflow` 선언 **0건** — 3-2절의 핀이 그대로
  유지됨을 확인한다. 근거가 바뀌어도 핀은 같다.
- `.ax-surface:not( [open] )`이 `display: none`을 선언한다. **대가를 치르고 배운 핀이다**:
  행이 `display: flex`를 선언하면 작성자 스타일이 UA의
  `dialog:not([open]) { display: none }`를 이기므로, 페이지의 **닫힌 surface가 전부 한꺼번에
  그려진다.** 스타일북에서 소유자가 발견했다. 열린 것만 재면 보이지 않는 결함이라
  "닫힌 상태에서 박스가 0개"가 런타임 핀에도 함께 있어야 한다.
- `.ax-scaffold__body`가 sheet 열을 가질 때 `grid-template-columns`에
  `minmax( 0, 1fr )`이 남아 있다.

JSX 핀.

- Surface 소스에 `createPortal` **0건**(3-1절).
- `Scaffold`의 `sheet` slot이 `__body` 안, `__main` 뒤.

런타임 핀.

- 아무것도 열지 않은 페이지에서 모든 `.ax-surface`의 `getClientRects().length`가 **0**이다.
- standard sheet가 compact에서 열릴 때 `dialog.open`이 참이고 modal 상태다. 같은 ref,
  같은 노드(5절).
- body에 focusable이 있을 때 `tabindex`가 **없다**(7절).

### 10-1. 리뷰가 찾은 세 결함 (2026-10-11)

구현 직후 리뷰에서 셋이 나왔다. 전부 실재했고, 전부 고친 뒤 **끄고 회귀를 재현해서**
결박을 확인했다.

**1. medium 전환이 반쪽이었다.** CSS가 폭·shape·elevation만 바꾸고 scrim·headline
typescale·motion은 full-screen 행의 것을 남겼다. 그래서 medium에서 **투명 backdrop 위에
떠 있는 basic 모양 dialog**가 나왔다. 블록 stylesheet가 이 질문의 답을 주석으로 들고
있었다 — basic의 "size, surface, headline and speed"를 가져가고 "its content is unchanged:
the header keeps its close button and its action". 즉 **anatomy는 건너가면 안 된다.**
리뷰는 actions 위치까지 옮기라고 했는데 그 부분은 채택하지 않았고, 근거는 블록 주석과
M3의 같은 문장이다.

scrim이 특히 미묘하다. 행 기록은 `scrim: false`이고 그것은 **행이 적용되는 창에서** 참이다
— 창을 채운 dialog는 가릴 것이 없다. medium부터 해소된 행이 basic이므로 scrim도 따라온다.
**scrim은 선언된 행이 아니라 해소된 행의 함수다.** 블록이 presentation 예외 없이 모든
dialog에 scrim을 그리는 이유가 그것이다.

구현상 결론 하나가 따라온다. `data-scrim`처럼 **렌더 시점에 정해지는 속성은 media query에
답할 수 없다.** 그래서 scrim 정책은 JS에서 CSS로 옮겼고, 컴포넌트는 backdrop을 무조건
페이드한다. 투명한 backdrop을 페이드하는 것은 아무것도 칠하지 않는다.

`surface.yml` `adaptive`에 `transfers`와 `keeps`를 적어 "becomes"가 어디까지인지 더는
해석에 맡기지 않는다.

**2. native close가 controlled `open`과 분리됐다.** `cancel`만 연결하고 `close`는 받지
않았다. 계약이 기록한 `<form method="dialog">`로 닫으면 브라우저가 dialog를 닫고 아무도
알려주지 않는다 — 호출자의 `open`은 참으로 남고, effect의 의존이 바뀌지 않아 다시 돌지
않으며, 다음 트리거는 이미 가진 값을 다시 설정해 아무것도 렌더하지 않는다. **surface가
영구히 열리지 않는다.** 끄고 재현했다: `expanded`가 `true`로 굳고 재오픈 시도가 실패한다.

고친 뒤에는 `selfClosingRef`가 컴포넌트 자신의 `close()`를 표시해, exit 모션 끝의 close가
`onClose`를 다시 부르지 않는다. 외부 close만 호출자에게 보고된다.

**3. scroll affordance가 동적 body에서 썩었다.** 두 조건 모두 **region의 box를 바꾸지 않고**
바뀔 수 있다. `max-block-size` 안에서 콘텐츠가 늘면 `scrollHeight`만 커지고, 링크가 생기거나
사라지면 어떤 box도 안 바뀐다. ResizeObserver 하나로는 못 본다.

MutationObserver(childList·subtree·focusability 속성)와 region + 자식들에 대한
ResizeObserver를 함께 둔다. 자식까지 보는 이유는 늦게 로드되는 이미지처럼 mutation이
없는 변화가 있기 때문이다. 끄고 재현했다: 링크를 넣어도 `tabindex="0"`이 남는다.

**핀을 만들며 배운 것 둘.**

- `"MutationObserver" in core`로 핀을 만들었더니 생성자를 없는 이름으로 바꿔도 통과했다.
  주석과 feature guard에 그 단어가 남아 있었기 때문이다. **단어가 아니라 배선에 묶어야
  한다** — 생성자 호출과 `observe(region, { … })`의 옵션까지.
- `keeps`를 "목록에 있으면 검사한다"로 짰더니 목록에서 지우면 조용히 통과했다. 한 방향
  핀이다. 컴포넌트가 렌더하는 부위는 **기록이 반드시 이름을 대도록** 양방향으로 묶었다.

그리고 그 양방향 핀이 바로 내 추론 하나를 잡았다. full-screen body에 `padding-block: 24 0`을
줬는데, 발행된 measurements는 `{ top: 24, inline: 24 }`뿐이고 bottom은 **발행되지 않았다.**
침묵을 0으로 읽은 것이고, 그래서 medium에서 되돌리는 override가 필요했고, 그 override가
`keeps`의 body를 건드렸다. 대칭 padding으로 바꾸니 override가 사라졌다.

### 측정 환경 주의

이 펜에서는 `visibilityState`가 `visible`이고 뷰포트가 1024x768인데도
**`requestAnimationFrame`이 돌지 않는다.** `document.timeline.currentTime`은 전진한다(916ms
측정). 그래서 WAAPI 애니메이션이 `running`에 영원히 머물고 `finished`가 해소되지 않아, exit
모션 뒤의 `close()`가 실행되지 않는다. **컴포넌트 결함이 아니다.** 검증하려면
`getAnimations({ subtree: true }).forEach( a => a.finish() )`로 직접 끝내고 `open`이 내려가는
것을 본다. 저장소 메모는 "숨겨진 펜은 0x0에 rAF 없음"으로 적혀 있는데, 보이고 크기도 정상인
펜에서도 rAF가 없을 수 있다.

## 11. 미결

소유자 판정 세 건(3·4·8절)은 2026-10-11에 승인됐다. 아래는 측정할 것이 아직 없어서
남은 것들이다.


- **supporting pane과 standard side sheet가 동시에 열리면.** 열을 둘 두는지, sheet가
  supporting을 덮는지, 아니면 상호 배타인지. 지금 둘을 함께 쓰는 route가 없으므로 측정할
  것이 없다. 첫 공존 route가 정한다.
- **~~admin bar offset~~.** 해당 없음으로 닫혔다. 앱 라우트는
  `axismundi_capstone_hide_public_admin_bar`(`includes/route.php`)로 admin bar를 **발생
  지점에서** 끄고, 그러면 마크업·스타일시트·인라인 document-offset 규칙이 함께 사라진다.
  밑에 깔 바도, 되돌릴 offset도 없다. 프론트앱은 자기 app bar를 갖는다.
- **standard sheet가 `<html>`의 scroll을 건드리지 않는 것의 결과.** 페이지가 좁아져도 문서
  스크롤은 그대로이므로, sheet 안의 inner scroll과 문서 스크롤이 동시에 존재한다. side sheet는
  "scrolls vertically, independent of the page; both keep their position"이라 이것이 발행된
  거동이지만, 휠이 어느 쪽을 먼저 먹는지는 재야 한다.
- **full-screen dialog 위의 basic dialog**(`surface.md` 결정 10). top layer는 쌓이므로
  플랫폼이 허용한다. React 쪽에서 둘을 동시에 열 route가 아직 없다.

## 관련 기록

- `DECISION-FRONTEND-SCROLL-OWNERSHIP.md` — 3-2절의 조건부 금지와 3-3절의 inner scroll 전제.
- `DECISION-FRONTEND-OVERLAY-SURFACE-OWNERSHIP.md` — 평행 Surface 판정과 구현 순서.
- `DECISION-FRONTEND-LAYOUT-NAMING.md` — Scaffold slot의 공개 어휘.
- `products/styleguide/_data/surface.yml` — 수치와 금지 조합의 공유 지점. `conflicts`의
  `initial focus`가 6절의 상위 판정이다.
- `products/styleguide/_components/surface.md` — 블록 쪽 결정 11개. 2·3·4·7·8·10번이 이
  문서와 같은 질문의 블록 측 답이다.
- `REFERENCE-M3-DIALOGS-AND-SHEETS.md` — 아홉 페이지 원문 요약.
