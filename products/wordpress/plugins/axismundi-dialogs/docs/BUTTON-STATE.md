# Button state and stateful labels — design memo

상태: **설계 메모, 구현 보류** (2026-09-14)

`selectedText`(선택 상태 라벨)는 넣지 않았다. 이 문서는 보류 결정의 근거와, 다시 착수할 때 쓸 조사 결과를 남긴다.
같은 날 커밋된 icon transition / hover preview(`17a818a`)는 이 결정과 무관하게 유지된다.

## 1. 보류한 이유

- 그룹 전용으로 제한하면 주 사용처를 놓친다. Label swap이 필요한 대표 사례(`Start/Stop`, `Play/Pause`, `Like/Liked`)는
  여러 개 선택 그룹이 아니라 **단독 버튼**이나 **도메인 플러그인의 IAPI 상태**에서 나온다.
  그룹 안의 버튼은 오히려 고정 이름 + `aria-pressed` toggle이 가장 자연스럽다.
- 단독 `togglable` 버튼의 상태 계약이 아직 없다(§3).
- Like 계열 문구와 접근 가능한 설명은 소비자 플러그인(Activities)이 i18n으로 소유해야 한다.
  지금 일반 속성으로 넣어도 올바른 소비자가 없다.

재착수 조건: Form 또는 Activities에서 실제 소비 사례가 생길 때.

## 2. 두 계약 (합의된 구분)

| 구성 | 고정 라벨 toggle | 대체 라벨 상태 버튼 (stateful command label) |
|---|---|---|
| 의미 | 현재 상태가 켜졌는가 | 누르면 실행될 명령 / 현재 상태를 말하는 문구 |
| 상태 속성 | `aria-pressed` | `data-selected` (ARIA 상태 없음) |
| 접근 가능한 이름 | 고정 | 현재 보이는 라벨 |
| 시각 변화 | fill, 색, shape, selected icon | 같음 + 라벨 교체 |
| 대표 사례 | Favorite, Bookmark, filter chip, segmented | Start/Stop, Play/Pause, Follow/Unfollow |

- 두 계약을 한 버튼에서 섞지 않는다. "Stop, pressed"는 명령 라벨과 상태가 충돌한다
  (APG Button pattern: toggle의 라벨은 상태에 따라 바뀌면 안 된다).
- `Like → Liked`는 명령이 아니라 상태 문구다. 기본은 고정 라벨 toggle을 권한다.
  꼭 `Liked`로 바꾸려면 이름에 결과를 밝혀야 한다(예: "Liked. Activate to remove like") — 이 문장은 동사 활용·다국어 때문에
  블록이 자동 생성하지 않고 소비자가 번역해 제공한다. 블록에 `selectedDescription` 같은 속성은 두지 않는다.
- 단일 선택 그룹(segmented)과 surface trigger(`aria-expanded`)는 항상 고정 라벨이다.
  disclosure 버튼도 APG상 상태에 따라 라벨을 바꾸지 않는다("Open menu → Close menu" 금지).

## 3. 현재 코드의 사실 (2026-09-14 실측·확인)

- **toggle 상태는 Dialog Button Group 안에서만 생긴다.**
  `aria-pressed`, `data-wp-bind--aria-pressed="state.isPressed"`, `data-wp-on--click="actions.toggle"`은
  그룹의 `render_block_axismundi/dialog-button-group` 필터(`axismundi-dialogs.php`, `axismundi_dialogs_button_group_selection`)가 붙이고,
  런타임은 `blocks/dialog-button-group/view.js`다.
- **단독 `togglable` 버튼은 상태를 만들지 못한다.** 테스트 페이지에서 그룹 밖 `togglable: true` 아이콘 버튼에
  `aria-pressed`가 없었다. 반면 `includes/icon.php`는 stateful로 판정해 두 아이콘을 렌더하므로,
  선택 아이콘이 설정돼 있어도 영원히 unselected 아이콘만 보인다. → 별도 과제: standalone toggle 상태 모델.
- surface trigger의 `aria-expanded`는 dialog 쪽 런타임(`blocks/dialog/view.js`)이 관리한다. 그룹 런타임과 별개.

## 4. Label swap 조사 결과 (재착수 시 출발점)

### 마크업

두 라벨을 한 grid 셀에 겹친다. 아이콘의 `.ax-icon-swap`과 같은 방식이라 폭이 긴 쪽으로 고정되고 fade 전환을 재사용할 수 있다.

```html
<!-- Button -->
<span class="ax-label-swap">
  <span class="ax-label--unselected" aria-hidden="false">Start</span>
  <span class="ax-label--selected" aria-hidden="true">Stop</span>
</span>

<!-- Icon Button: the same pair as visually hidden text -->
<span class="screen-reader-text ax-label--unselected" aria-hidden="false">Play</span>
<span class="screen-reader-text ax-label--selected" aria-hidden="true">Pause</span>
```

### 접근 가능한 이름은 `aria-hidden`이 결정한다 — CSS `visibility`에 맡기지 않는다

- CSS `visibility`는 전환 중 한쪽 끝이 `visible`이면 전환 구간 전체가 `visible`로 보간된다.
  fade 동안 두 라벨이 모두 이름 계산에 들어갈 수 있다.
- 그래서 CSS는 fade/폭 고정만 담당하고, 이름은 상태에 묶인 `aria-hidden`이 정한다.
- IAPI로 새 JS 없이 된다: 각 라벨 span에 `data-wp-bind--aria-hidden`을 상태 getter에 묶고,
  서버도 초기값을 직접 쓴다(그룹 필터가 `aria-pressed`를 "written as well as bound"로 쓰는 것과 같은 방식).
  서버 파생 state closure(`wp_interactivity_state` + `wp_interactivity_get_context`)가 없으면 bind가 null로 풀려 속성이 지워진다.

### `screen-reader-text`는 해법이 아니다

- 이 클래스는 화면에서만 잘라낼 뿐 이름 계산에서 빠지지 않는다. 두 개를 넣으면 둘 다 읽힌다.
- `assets/tooltip.js`의 `LABEL = '.screen-reader-text'`는 **첫 번째** 일치를 읽는다. span이 둘이면 선택 상태에서도 첫 라벨이 뜬다.
  재착수 시 `.screen-reader-text:not([aria-hidden="true"])`를 읽도록 바꾼다.

### `aria-label` 복제보다 실제 텍스트 span

- 문자열이 한 곳에만 있다(보이는 라벨 = 이름 = 툴팁 원본).
- 브라우저 자동 번역은 본문 텍스트를 번역하지만 `aria-label`은 놓치기 쉽다.
- 보이는 라벨과 이름이 어긋나지 않는다(WCAG 2.5.3 Label in Name).

### 선례: 테마 스위처 (`axismundi-theme-switcher/blocks/theme-switcher/render.php`)

- 세그먼트 버튼: 고정 라벨 span + `aria-pressed` → 고정 라벨 toggle 계약.
- 순환 버튼: `aria-pressed` 없음, 상태는 `data-theme-scheme`, 이름은 IAPI로 교체
  ("the accessible name below already says which") → 상태 버튼 계약.
  단, 순환 버튼은 `aria-label`과 sr span 텍스트를 JS로 바꾸는 방식이라 hydration 전 캐시 불일치를 보정해야 한다.
  겹친 두 span + `aria-hidden` 방식은 서버 렌더만으로 이름이 맞는다.

### 알려진 한계

- 포커스된 요소의 이름 변경은 스크린리더가 일관되게 알려주지 않는다. 상태 버튼 계약의 공통 한계다.
  필요하면 페이지 공용 live region 공지를 옵션으로 검토한다(기본값으로 넣지 않음).

## 5. 다음 설계 과제

1. **Standalone toggle 상태 모델** — 그룹 밖 `togglable` 버튼이 누구의 store에서 상태를 갖는가, 초기값(`selected`)과 런타임 값의 관계,
   영속은 소비자 몫이라는 원칙(테마 스위처 = Interactivity + cookie 선례) 유지. 그룹 필터와 같은 서버 파생 state 패턴.
2. **Stateful command label 계약** — 위 §2·§4를 소비자가 IAPI 상태로 연결하는 인터페이스
   (블록 속성인지, 소비자 플러그인이 context/state를 주입하는 seam인지). 도메인 문자열은 소비자가 소유.
