# 계획: Scaffold를 M3의 세 층에 맞춘다 — bars, rails, panes

## 상태

**진행 중. 2026-10-11.** 4절은 소유자 판정이 필요하다. 2절과 3절은 읽기이고, 5절은 그에서
따라 나온다. S1(sticky chrome)과 S2(safety region)는 같은 날 구현·검증됐다.

이 문서는 `SOURCE-M3-SCAFFOLD-RAW.md`를 읽고 쓴다. 그 세 페이지(`bars`, `rails`, `panes`)는
이 저장소의 레이아웃 캡처에서 빠져 있던 자리였고, 하필 Scaffold anatomy 그 자체였다.

닫으려는 것은 `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md`가 열어 둔 미결 다섯 중 셋이다.

```txt
supporting pane이 co-planar / floating / docked / reflowed / hidden 중 무엇인가   → 3절
pane이 resize 되는가, 폭이 지속되는가, drag handle 접근성 계약은 무엇인가          → 4-5절
어떤 M3 컴포넌트가 bar와 rail 영역을 차지하는가                                   → 2절
```

## 1. 숫자는 여기서 오지 않는다

이 문서는 수치를 새로 적지 않는다. 원본은 셋이다.

```txt
viewport.json                         breakpoint
DECISION-FRONTEND-ADAPTIVE-LAYOUT.md  fixed pane width (expanded 360px, large/XL 412px)
SOURCE-M3-*.md                        발행 원문
```

**Android 가이드의 비율은 채택하지 않는다.** `canonical-layouts` 가이드는 supporting pane을
"medium 50/50, expanded 70/30"으로 적지만, 이 저장소는 그 수치를 이미 한 번 걸러냈다 —
`PLAN-FRONTEND-CANONICAL-LAYOUTS.md`의 "정정: supporting pane은 비율이 아니라 고정 폭이다".
그리고 이번에 읽은 `panes` 페이지가 **저장소 기록 쪽을 확증한다**: snap 폭이 360dp와 412dp
이고, extra-large의 fixed pane 권장이 412dp다. Android 가이드의 비율은 그 가이드의 단순화다.

같은 종류의 오류가 Lab·VQA·Figma kit에서 반복해 걸러졌고, 이 문서를 쓰는 과정에서 또 한 번
났다. 샘플이나 2차 가이드의 수치를 계약처럼 옮겨 적지 않는다.

## 2. 측정 — 우리는 두 층이고 M3는 세 층이다

M3는 **bars → rails → panes**를 동심원 세 층으로 둔다. rails는 "the next level in layout
after bars, filling the perimeter space surrounding panes".

```txt
M3                          우리 (scaffold.css)                  상태
--------------------------------------------------------------------------
bars    top (app bar)       __app-bar                            있음
        bottom (nav bar)    __bar                                있음
        safety region       —                                    없음
rails   leading (nav rail)  __rail                               있음
        trailing(companion) —                                    없음
        toolbar (bar 위)    —                                    없음
        compact top/bottom  —                                    없음 (FAB·chat input 자리)
panes   1~3                 __main · __supporting · __sheet      있음
        fixed / flexible    grid가 이미 표현 (auto / minmax)      어휘만 없음
        permanent/temporary —                                    없음
        resize              —                                    없음
```

**이름은 살아남는다.** M3가 trailing 쪽을 "companion rail"이라고 부르므로 `__rail`을 leading
전용으로 두고 `__companion`을 나중에 더하면 된다. 공개 CSS 클래스는 함부로 바꾸지 않는다는
규칙에 걸리지 않는다.

**`panes` 페이지가 우리 Dialog 구현을 독립적으로 확증한다.** 접근성 절이 modal floating pane
에 대해 "Focus moves automatically to the **first element** in the pane, and when the pane is
closed, focus moves back to the element that triggered it, like a dialog"라고 적는다. 우리가
Chrome 152 측정으로 억지로 세운 규칙이 pane 쪽에서 같은 문장으로 나온다.

## 3. 가장 큰 발견 — Surface의 축과 pane의 축은 같은 축이다

`DECISION-FRONTEND-ADAPTIVE-LAYOUT.md`는 "supporting pane이 co-planar인가 floating인가
docked인가"를 미결로 뒀다. 그 질문은 **새 추상을 요구하지 않는다. 이미 만든 것이 답이다.**

M3의 "Displaying multiple panes" 세 가지가 `surface.yml`의 presentation과 일대일이다.

```txt
co-planar   나란히 놓인 두 pane          standard side sheet  = __sheet 열
floating    다른 pane 위에 뜨는 작은 pane  basic / full-screen dialog, modal side sheet
docked      창 가장자리에 고정된 pane      bottom sheet
```

M3가 직접 그렇게 쓴다 — floating의 예시가 "like a dialog", docked의 예시가 "like a bottom
sheet"다.

그리고 "How panes adapt" 세 전략이 우리 `adaptive` 규칙과 일대일이다.

```txt
show and hide   supporting pane이 840에서 나타남                    구현됨
levitate        standard side sheet가 compact에서 modal이 됨          구현·측정됨
                (co-planar -> floating. DOM은 안 움직이고 여는 방법만 바뀐다)
reflow          supporting pane이 세로 방향에서 main 아래로 내려감    미구현
```

**따라서 Scaffold에 새 pane 추상을 만들지 않는다.** Scaffold는 co-planar 자리를 주고,
floating과 docked는 Surface가 이미 소유한다. 미결이던 질문의 답은 "셋 다이고, 어느 것인지는
route가 Surface의 modality로 고른다"이다.

이 대응을 한 곳에 적어 두는 것이 이 절의 목적이다. 적어 두지 않으면 두 축이 따로 자라고,
같은 결정을 Surface 쪽과 layout 쪽에서 두 번 내리게 된다.

## 4. 결정 제안

### 4-1. 없는 rail 영역은 만들지 않는다. 이름만 정한다

소유자 판정. `Scaffold`는 companion rail, toolbar rail, compact rail 영역을 **지금 만들지
않는다.** 셋 다 소비자가 없다 — toolbar도 FAB도 chat input도 아직 컴포넌트가 아니다.

소비자 없는 grid 영역은 모양을 추측으로 정하게 되고, `Scaffold`가 컴포넌트를 제조하지
않는다는 규칙과도 어긋난다. 반면 **이름은 지금 정해 둔다.** 이름을 바꾸는 것은 비싸고
(공개 CSS 클래스), 정해 두는 것은 공짜다.

```txt
__companion   trailing rail. M3의 발행 명칭 "companion rail"
__toolbar     navigation bar 위의 rail 영역
```

### 4-2. safety region은 Scaffold가 소유한다

소유자 판정. M3는 bar를 safety region에 **상대적으로** 배치한다 — app bar는 위쪽 safety
region 바깥, navigation bar는 아래쪽 safety region 위. bar가 Scaffold의 영역이므로 그 기준선도
Scaffold의 것이다.

웹에서의 대응물은 `env(safe-area-inset-*)`다. 이것은 PWA에서 실제로 걸린다(notch, home
indicator). 그리고 이 앱 라우트는 admin bar를 끄므로 경쟁하는 offset이 없다 — 자립적으로
넣고 잴 수 있다.

**pane은 safety region에 닿지 않는다.** M3가 "The safety region shouldn't contain primary
content"라고 쓴다.

### 4-3. fixed / flexible은 어휘만 붙인다

grid가 이미 표현하고 있다 — `auto`가 fixed, `minmax( 0, 1fr )`가 flexible이다. 새 메커니즘이
아니라 이미 선 것에 M3의 이름을 붙이는 일이다.

하나는 강제한다: **flexible pane이 최소 하나.** M3가 "All layouts need at least one flexible
pane"이라고 쓰고, 이것은 어기면 레이아웃이 조용히 깨지는 종류다. 개발 중 `warning` 하나로
알린다. 렌더 중 throw하지 않는다는 규약 그대로다.

### 4-4. pane 상한 3은 강제하고, breakpoint별 권장 수는 강제하지 않는다

M3는 breakpoint마다 권장 pane 수와 대안을 함께 발행한다(compact 1 / medium 1 또는 2 /
expanded·large 2 또는 1 / extra-large 2 또는 1·3). **권장은 route의 선택이다** — 이미
`DECISION-FRONTEND-ADAPTIVE-LAYOUT.md`가 "어떤 destination이 몇 개의 pane을 쓰는가"를 route에
맡겼다.

반면 "Don't use more than three panes"는 상한이고 예외가 없다. 그것만 강제한다.

### 4-5. pane resize는 BottomSheet 핸들 다음이다

`panes` 페이지가 pane drag handle의 거동을 실제로 발행한다. 이 저장소가 "계약이 없다"고
적어 둔 자리인데, 있다.

```txt
drag        split-pane은 양쪽 자유 조정 또는 snap
            fixed-and-flexible은 fixed pane을 완전히 접고 편다
select      "should also toggle between layout sizes when selected"
snap        360dp · 412dp
지속성       persistent(기억함, 대부분의 경우) / temporary(안 기억함, supporting pane)
            persistent는 breakpoint가 바뀌어도 유지된다
```

select가 크기를 토글한다는 점에서 **바텀시트 핸들과 같은 모양**이다. 그래도 지금 공용
컴포넌트로 빼지 않는다 — 값 모델이 다르고(시트는 자기 높이, pane은 두 pane 사이이며 0까지
접힌다), ARIA도 시트만 `button`으로 발행돼 있다. **두 번째 사례가 모양을 확인해 준 뒤에
추출한다.** 소비자 하나로 그은 경계는 추론이라는, 이 저장소가 이미 네 번째 승격 기준으로
적어 둔 규칙 그대로다.

## 5. 구현 순서

```txt
S1  sticky chrome (app bar, rail)   scroll 기록 5절의 첫 미결을 닫는다
S2  safety region                   4-2
S3  pane 어휘 + flexible 불변식      4-3, 4-4
S4  reflow                          세로에서 supporting이 아래로. 측정 먼저
S5  pane resize + drag handle        BottomSheet 핸들이 선 뒤 (4-5)
```

S1이 먼저인 이유는 중요도가 아니라 **이미 깨져 있기 때문**이다. scroll 기록 5절이
"chrome persistence는 0이다 — `position: sticky`가 아직 어디에도 없다"고 적었고, SideSheet가
standard 모드에서 sticky를 처음 썼다. app bar와 rail이 따라오지 않으면 같은 페이지에서
한 쪽만 남는다.

S4를 S5보다 앞에 두는 이유는 reflow가 **CSS만으로 끝나고** resize는 상태·지속성·포인터를
요구하기 때문이다. 값싼 것을 먼저 세운다.

## 6. 미결 (소유자 판정)

- **medium에서 어느 navigation surface인가.** 이미 열려 있는 미결이고 이 문서가 닫지 않는다.
  M3가 bar와 rail을 둘 다 허용하고 제품이 정하라고 한 자리다.
- **resize의 기본값이 persistent인가 temporary인가.** M3는 "대부분의 resizable layout에는
  persistent"라고 권하고, supporting pane에는 temporary를 든다. 우리 route가 어느 쪽이
  많을지는 아직 route가 없어서 모른다.
- **companion rail의 첫 소비자가 무엇인가.** 영역을 만들 시점은 그때다.
- **three-pane을 extra-large에서 쓸 것인가.** standard side sheet가 세 번째 pane이 되는
  구성이고, 부품은 이미 전부 있다(SideSheet가 standard를 지원한다). 쓸 route가 있는지가
  질문이다.

## 관련 기록

- `SOURCE-M3-SCAFFOLD-RAW.md` — 이 문서가 읽은 세 페이지의 원문.
- `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md` — 어휘와 fixed pane 폭 토큰. 미결 셋을 이 문서가
  좁힌다.
- `DECISION-FRONTEND-SCROLL-OWNERSHIP.md` — 1절의 sticky chrome, 5절의 미결(S1).
- `DECISION-FRONTEND-OVERLAY-ESCAPE-AND-PUSH.md` — 4절의 sheet 열, 5절의 levitate.
- `DECISION-FRONTEND-LAYOUT-NAMING.md` — `Scaffold` 이름과 CSS 티어 경계.
- `PLAN-FRONTEND-CANONICAL-LAYOUTS.md` — 승격 기준과 supporting pane 폭 정정(1절).
- `products/styleguide/_data/surface.yml` — 3절의 대응이 사는 곳.
