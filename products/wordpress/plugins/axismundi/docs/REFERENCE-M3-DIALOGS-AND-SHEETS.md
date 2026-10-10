# 참조: M3 Dialogs · Bottom sheets · Side sheets 원문 정리

## 이 문서는 무엇인가

m3.material.io의 아홉 페이지(각 컴포넌트의 specs / guidelines / accessibility)를
2026-10-11에 읽고 **구현에 쓰이는 문장과 수치만** 남긴 것이다. 토큰 표가 아니다 —
토큰은 `m3-token-tables/SOURCE-M3-{DIALOGS,BOTTOM-SHEETS,SIDE-SHEETS}-RAW.md`에 있고,
여기는 그 표가 담지 않는 **행동·배치·접근성 문장**이다.

페이지는 CSR이라 초기 HTML에 본문이 없다. 7초 이상 기다린 뒤 `main`의 `innerText`를
읽으면 나온다. 같은 이유로 badge 토큰 표가 한 번 "관측 실패"로 기록됐다가 대기 시간을
늘려 복구됐다.

읽은 URL은 `products/styleguide/_data/surface.yml`의 `meta.sources`에 이미 있다.

## 왜 요약이 아니라 기록인가

이 저장소에서 반복해 확인된 것: **원문 문장이 없으면 검증기를 쓸 수 없다.** 캐러셀의
불일치 25건, List의 화살표 누락, Tabs의 indicator 배치, Badge의 앵커가 전부 "프로즈가
무엇을 말하는가"에서 갈렸다. 그래서 수치만이 아니라 **판정을 가르는 문장을 그대로**
남긴다.

---

# Dialogs

## 발행 수치 — Basic dialog

    container shape          28dp
    container height         dynamic
    container width          min 280dp / max 560dp
    divider height           1dp
    icon size                24dp
    alignment with icon      center
    alignment without icon   start          <- 아이콘 유무가 정렬을 바꾼다
    padding top/left/right/bottom   24dp
    padding between buttons         8dp
    padding title <-> body          16dp
    padding icon <-> title          16dp
    padding body <-> actions        24dp

색: surface-container-high / secondary(icon) / on-surface / on-surface-variant
    / primary(button label) / scrim

## 발행 수치 — Full-screen dialog

    container shape          0dp
    container width          container width, max 560dp
    header height            56dp
    header width             container width
    headline alignment       start
    divider height           1dp
    close icon size          24dp
    bottom action bar        56dp 높이, container 폭
    padding top/left/right   24dp
    padding between elements 8dp

## 행동 규칙 (전부 prose)

- dialog = modal window. 나타나면 앱 기능 전체를 비활성화한다.
- **actions는 최대 두 개.** 하나면 acknowledgement, 둘이면 confirming + dismissing.
- confirming이 trailing edge에 가장 가깝다. RTL에서 자동으로 뒤집힌다.
- dismissive를 confirming 오른쪽에 두지 않는다.
- 쌓을 때는 confirming이 dismissive **위**.
- 세 번째 action(Learn more 등)은 비권장 — dialog를 미완 상태로 떠나게 한다.
- confirming은 선택 전까지 비활성화할 수 있다. dismissive는 절대 비활성화하지 않는다.
- **full-screen dialog는 compact 전용.** medium/expanded에서는 basic dialog.
- full-screen은 그 위에 다른 dialog가 올라올 수 있는 **유일한** dialog다.
  (full-screen을 열면 앱의 perceived elevation이 리셋된다)
- full-screen을 저장 없이 닫으면 basic dialog가 앞에 떠서 폐기를 확인한다.
- full-screen app bar의 유일한 내비게이션은 close X.
- 긴 headline은 app bar가 아니라 content 영역으로.
- scrolling: 내용이 넘쳐도 **title은 위에, buttons는 아래에 고정**.
- "Dialogs don't scroll with elements outside of the dialog, such as the
  background."   <- surface.yml 1-4절이 기대던 문장
- medium: 기본 center. 위치는 override 가능.
- expanded: scrim 위 modal window. custom position 가능하되 **가장자리 56dp 여백** 존중.

## 접근성

- 키보드: Tab / Shift+Tab이 dialog **안에서 순환**(마지막 다음은 첫 번째).
          Space/Enter가 포커스된 요소를 실행. **Escape가 닫는다.**
- 접근 이름은 보통 dialog의 title / headline.
- 200% 텍스트에서 headline은 4줄 안. 넘으면 전체 내용에 한 번의 탭으로 닿는 대안.
- dialog 안의 요소들은 각자의 접근성 지침을 따른다.

## 블록 구현·플랫폼과 충돌하는 지점 셋  ← 판정 필요

### C1. 최초 포커스

    M3 accessibility   "focus should automatically land on the first
                        interactive element within the dialog"
    블록 구현           dialog 자신에 포커스. 첫 컨트롤이 아님.
                        (author의 autofocus는 존중)

양쪽 다 근거가 있다. WAI-ARIA APG는 dialog 자신 또는 첫 요소를 모두 허용하고,
"첫 요소가 파괴적 액션이면 dialog나 덜 위험한 요소에" 둔다. M3는 그 조건 없이
첫 요소를 말한다. 블록 쪽이 왜 그렇게 했는지는 render.php 주석을 더 읽어야 한다.

### C2. role

    M3   "On web, basic dialogs should have the **alert dialog** role."
    HTML <dialog>.showModal()의 기본 role은 dialog

alertdialog는 APG에서 "사용자 응답을 요구하는 알림"이다. basic dialog 전부가
alertdialog인지, 확인을 요구하는 것만인지 M3 문장만으로는 갈리지 않는다.

### C3. full-screen = compact 전용

    M3   "Full-screen dialogs are for compact breakpoints only. For medium and
          expanded breakpoints, use a basic dialog."

이건 Surface의 presentation x breakpoint 축과 직접 맞물린다. surface.yml의
adaptive 절이 이 문장을 들고 있는지 확인해야 한다.


---

# Bottom sheets · Side sheets

## Bottom sheet — 발행 수치

    width                       full, max 640dp
    top margin                  72dp
    top margin (window > 640)   56dp
    start/end margin (> 640)    56dp          <- 640 넘으면 떠 있는 상자가 된다
    height                      variable
    drag handle alignment       center
    drag handle padding t/b     22dp

색: surface-container-low (container) · on-surface-variant (handle) · scrim
    modal만 scrim. standard은 scrim 없음. 그 외 두 변형은 같은 스펙.

## Bottom sheet — 행동

- standard: 메인 UI와 **공존**. 둘 다 보고 조작. 예) 지도 위 위치 정보, 음악 플레이어
  full-screen 높이일 때 app bar에 collapse 아이콘.
- modal: dialog처럼 앞을 막는다. **모바일 앱 전용.**
  초기 높이는 **화면의 50%를 넘지 못한다.** 넘치면 끌어올려 전체화면 + 내부 스크롤.
  닫는 법 넷: 메뉴 항목 선택 / scrim 탭 / 아래로 스와이프 / app bar의 close
- drag handle: 선택하면 preset 높이들을 **순환하거나 닫는다.** scrim 선택은 **항상 닫는다.**
  preset이 여럿인데 drag handle을 못 쓰면 **단일 포인터 대안이 필수**(Material requires).
- compact: 화면 폭 전체. medium/expanded: 기본 max-width(override 가능).
  **expanded에서는 side sheet으로 바꿔도 된다.**  <- presentation x breakpoint

## Bottom sheet — 접근성

- 상단 **48dp**가 resize 가능할 때 상호작용 영역.
- drag handle은 **tab order에 들어가고** role은 **button**, 라벨을 가진다.
- Tab -> drag handle. Space/Enter -> 높이 토글.
- **"Label only the drag handle."**  시트 자체에 라벨을 더 붙이지 않는다.
- 드래그로 되는 모든 동작에 단일 포인터 대안을 둔다.

## Side sheet — 발행 수치 (standard과 modal이 같다)

    start/end padding            24dp
    start padding with icon      16dp   (modal)
    padding between top elements 12dp
    bottom actions height        72dp
    bottom actions top padding   16dp
    bottom actions bottom padding 24dp
    bottom actions alignment     Left          <- 원문 표기가 "Left"다. start 아님
    max-width                    400dp
    margins (when detached)      16dp

색이 변형마다 다르다:
    standard  surface                 + outline-variant(divider) + on-surface-variant
    modal     surface-container-low   + on-surface-variant

## Side sheet — 행동

- standard: medium~expanded 주력. 주 콘텐츠와 **함께 보인다.**
- modal: compact에서 선호. 같은 내용이지만 **닫아야** 아래를 조작할 수 있다.
- 폭은 고정, 보통 화면 높이를 가득 채운다.
- 배치: **오른쪽 가장자리** 권장 — 왼쪽의 내비게이션 요소와 겹치지 않도록.
  16dp 정도 inset 가능. 그 이상 들이면 스크롤 동작이 불분명해진다(Don't).
- **"When a standard side sheet opens, the body area shrinks to accommodate the
  sheet's width while maintaining a margin on the body's trailing edge."**
  <- 블록 구현의 push 동작이 이 문장이다
- RTL: **왼쪽** 가장자리에, 모든 요소가 뒤집힌다.
- **"Side sheets can vertically scroll independent of the rest of the UI...
  Side sheets cannot scroll horizontally."**
  <- inner scroll이 **요구사항**이다. scroll-ownership 3-3절의 전제조건이 여기 걸린다
     (스크롤 컨테이너에 tabindex=0, 접근 가능한 이름, 그 이름이 pane 역할과 일치)

## Side sheet — 접근성

- **close affordance가 항상 있어야 한다** (Material requires).
  없으면 사람이 이 시트가 일시적인지 영구적인지 알 수 없다.
- Tab -> 비활성 아닌 icon button. Space/Enter -> 실행.
- **role은 Dialog.**

## role이 presentation마다 다르다  <- 계약으로 삼을 것

    basic dialog    alertdialog   (dialogs/accessibility)
    side sheet      dialog        (side-sheets/accessibility)
    bottom sheet    명시 없음. drag handle만 button으로 라벨하라고 함

## 불일치 기록 후보

### D1. (철회) bottom sheet의 가로 스크롤은 불일치가 아니다

    "Bottom sheets can be horizontally scrolled, independent of the rest of the
    screen's content."

그림이 가리키는 것은 시트 자신이 아니라 **시트 안의 내용**이다 — 지도 위 시트에
"Latest in the area" 카드가 가로로 흐른다. 바로 아래 캡션은 내용이 초기 높이를
넘으면 (세로로) 스크롤되어야 한다고 말한다. 둘은 같이 참이다.

side sheet은 그것을 금지한다 — "A side sheet's narrow width leaves limited space
to fully view items." 폭이 400dp이기 때문이다.

그래서 이것은 **두 시트의 실제 차이**이고 계약으로 삼을 값어치가 있다:

    bottom sheet (max 640dp)   가로로 흐르는 내용 허용. Carousel을 담을 수 있다
    side sheet   (max 400dp)   금지. 가로 스크롤을 암시하는 레이아웃도 금지

### D2. bottom actions alignment "Left"
    RTL 절은 모든 요소가 뒤집힌다고 한다. 그러면 Left는 start의 느슨한 표기다.
    논리 속성으로 구현하고 원문 표기를 기록한다.

### D3. modal bottom sheet "mobile apps only"
    우리는 웹이고 compact 윈도우가 모바일이 아닐 수 있다.
    surface.yml의 adaptive 축이 이걸 어떻게 읽었는지 확인 필요.

## 블록 구현과 대조한 결과 (view.js 머리말)

    "bottom sheet opens no higher than half the window" = 50% 규칙      OK
    "its drag handle raises it to its full height - 72dp below the top" = top margin 72dp  OK
    standard side sheet push = body area shrinks 문장                    OK
    599px compact 경계                                                   layout.yml
    시트 모션은 M3 미발행이라 자체 결정                                   맞음 (발행 없음 확인)

블록 구현이 프로즈를 정확히 읽었다. 승계 가능한 판정이다.
