# Material 3 Split button — the published differences

/ 캡처 2026-10-03, m3.material.io overview / specs / guidelines.
/
/ Button을 규범적 의존성으로 두고 **차이만** 적는다. 색 매핑, state layer, label
/ typography, disabled 처리는 전부 Button 것을 그대로 쓴다 — M3 자신이 "Split buttons
/ use the same colors and state layers as buttons and icon buttons"라고 하고, 그 표를
/ 여기 복사하면 체커 없는 두 번째 사본이 된다.

## Button에서 그대로 오는 것

```text
색 매핑          filled · tonal · elevated · outlined
state layer      hover 8% · focus 10% · pressed 10% · disabled 10%/38%
label typography 사이즈별 label-text-*
container height 32 / 40 / 56 / 96 / 136
```

색 스타일이 **넷**이다. Button의 다섯에서 Text가 빠지고, IconButton과 달리 Elevated가
남는다. 세 컴포넌트가 각자 다른 집합을 갖는다.

## Split button 고유

### 기하

| | XS | S | M | L | XL |
|---|---:|---:|---:|---:|---:|
| between space (seam) | 2 | 2 | 2 | 2 | 2 |
| inner corner | 4 | 4 | 4 | 8 | 12 |
| inner corner hovered / pressed | 8 | 12 | 12 | 20 | 20 |
| leading button leading space | 12 | 16 | 24 | 48 | 64 |
| leading button trailing space | 10 | 12 | 24 | 48 | 64 |
| trailing button icon | 22 | 22 | 26 | 38 | 50 |
| trailing button leading / trailing space | 13 | 13 | 15 | 29 | 43 |
| menu icon offset when unselected | −1 | −1 | −2 | −3 | −6 |

outer corner는 모든 사이즈에서 `50%`(full pill), trailing의 selected inner corner도 `50%`.

**seam은 사이즈와 무관하게 2dp다.** connected button group과 같은 값이고, 둘 다 붙어 있는
표면 사이의 간격이라는 점에서 같은 성격이다.

**XS와 S만 leading이 비대칭이다**(12/10, 16/12). M 이상은 대칭이다. M3가 "Text and icons
are optically centered when the buttons are asymmetrical"이라고 적는 이유가 이것이다.

### trailing 폭이 곧 48dp 타깃이다

```text
XS  13 + 22 + 13 = 48      S  13 + 22 + 13 = 48
M   15 + 26 + 15 = 56      L  29 + 38 + 29 = 96      XL  43 + 50 + 43 = 136
```

XS와 S의 trailing이 정확히 48이다. M3가 아이콘 버튼에는 48dp 타깃 규칙을 따로 두고
(`icon_button.yml`의 `min_target`), split button에는 두지 않는 이유가 여기 있다 — **폭 자체가
이미 48이라 둘 자리가 없다.** M 이상은 컨테이너 높이와 같아 정사각이 된다.

### IconButton을 trailing으로 쓸 수 없다

같은 사이즈에서 값이 다르다. 끼워 넣으면 두 계약이 싸운다.

```text
          trailing icon   trailing 폭
S         22dp            48       (split button)
S         24dp            40       (icon button, default width)
```

그래서 SplitButton은 자기 두 버튼을 직접 그린다. Button/IconButton 인스턴스를 담는
ButtonGroup과 반대다 — ButtonGroup의 자식은 독립 컴포넌트고, 여기 두 조각은 이 컴포넌트의
부품이다.

### 선택되어도 색이 바뀌지 않는다

> the split button color doesn't change when selected — only a state layer is applied

toggle button과 다른 지점이다. Button·IconButton의 toggle은 선택되면 container 역할 자체가
바뀌는데, split button은 state layer만 얹는다. 따라서 **toggle 색 표를 가져오면 안 된다.**

### 열림 상태는 모양과 회전으로만 말한다

```text
trailing inner corner   선택 시 50%
menu icon               안쪽으로 180° 회전
motion                  standard motion scheme (expressive 아님 — 원문이 명시)
icon 위치               선택되면 광학 보정이 풀리고 정중앙으로
```

회전과 모양 변화가 Expressive가 추가한 정의적 동작이다. 메뉴가 없어도 `[aria-expanded="true"]`
로 구현할 수 있고, 그래야 나중에 Menu가 붙을 때 SplitButton을 고치지 않는다.

## ARIA는 지금 달지 않는다

lab의 `SPLIT-BUTTON-SPEC-AUDIT.md`가 최종 형태를 적어 두었다.

```text
parent     <div role="group" aria-label="...">
leading    <button>, aria-haspopup 없음
trailing   <button data-popover-trigger aria-haspopup="menu" aria-expanded aria-controls>
```

**`aria-haspopup`과 `aria-expanded`는 v1에서 달지 않는다.** 메뉴가 없는데 달면 ARIA가
거짓말을 한다. SplitButton은 trailing에 임의 속성을 전달할 자리만 열어 두고, 그 속성들은
나중에 Menu 쪽이 공급한다. CSS는 지금부터 `[aria-expanded="true"]`를 스타일링한다.

lab이 `popover/`에 open/close·outside click·Escape·focus 복원을 모두 맡긴 것도 그대로
유지한다. SplitButton은 아무것도 토글하지 않는다.

## 메뉴 배치 (Menu 붙일 때)

```text
trailing button 에 정렬, 공간이 없으면 버튼의 한 변에 정렬
split button 에서 4dp 떨어뜨린다
```

## 출처

- <https://m3.material.io/components/split-button/overview>
- <https://m3.material.io/components/split-button/specs>
- <https://m3.material.io/components/split-button/guidelines>
- `products/reference-implementations/axismundi-lab/modules/split-button/docs/` — DOM·ARIA 경계와
  popover 책임 분리. evidence이지 값의 authority는 아니다.
