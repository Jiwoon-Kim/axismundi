# 결정 기록: Social Frontend Component Conventions

## 상태

채택됨. 2026-10-03.

Icon, Elevation, Button, Icon button 넷을 만들면서 반복된 것만 적는다. 추측은 넣지 않는다.
이 문서의 목적은 **다음 컴포넌트가 같은 자리에서 시작하게 하는 것**이고, 특히 세션이 바뀌거나
컨텍스트가 압축된 뒤에도 규약이 사람의 기억이 아니라 저장소에 남아 있게 하는 것이다.

## 착수 순서 — `_components/*.md`를 `_data/*.yml`보다 먼저

이 순서가 첫 줄에 있는 이유는 어긴 적이 있기 때문이다. Button을 만들 때 `_data/button.yml`과
어댑터 CSS만 읽고 `_components/buttons.md`를 읽지 않아서, 저장소가 이미 내려놓은 결정을
뒤집었다 — trailing icon slot을 추가했고, 스타일북에 "한 버튼에 아이콘 두 개"라는 명시된
Don't를 시연까지 했다.

```text
1  products/styleguide/_components/<name>.md     무엇을 결정했고 무엇을 일부러 안 했는가
2  products/styleguide/_data/<name>.yml          발행된 수치
3  products/styleguide/assets/css/components/    어댑터 구현과 그 주석
4  tools/validators/validate_styleguide_*.py     무엇이 강제되는가
5  axismundi-dialogs 등 블록 선례                 같은 문제를 이미 푼 자리
6  m3.material.io                                JS 렌더라 브라우저로 캡처
```

`_data/*.yml`에는 **값**이 있고, `_components/*.md`에는 **왜 그 값이고 무엇을 안 만들기로
했는지**가 있다. 재구현이 조용히 작업을 버릴 수 있는 지점은 두 번째다.

## 토큰 tier와 slot 계약

`--md-ref-*` / `--md-sys-*` 위에 `--md-comp-*`를 둔다. Button이 그 tier의 첫 파일이었다.

**slot은 컴포넌트 단위이고, 그것이 공개 계약이다.** `filled`나 `tonal`, `secondary-container`는
slot이 아니라 slot에 어떤 값이 들어가는지의 규칙이다.

```css
.ax-button[data-variant="tonal"] {
	--md-comp-button-container-color: var( --md-sys-color-secondary-container );
	--md-comp-button-label-text-color: var( --md-sys-color-on-secondary-container );
}
```

색 역할로 slot 이름을 지으면 타이포그래피가 색 아래에 들어가고(`label-text-font`는
`secondary-container`의 것이 아니다), outline이 container인 Outlined와 container가 없는 Text가
각자 네임스페이스를 요구한다. Material Web처럼 variant마다 네임스페이스를 나누는 방식은
커스텀 엘리먼트 다섯에는 맞지만, prop으로 variant를 고르는 React 컴포넌트 하나에서는 다시
alias 층을 만든다.

### 한정자가 붙은 토큰은 후보, 안 붙은 것이 계약

축이 여럿일 때 해소 규칙은 하나다. 한 축이 후보를 발행하고 다른 축이 고른다.

```css
.ax-icon-button[data-size="small"] {
	--md-comp-icon-button-narrow-space: var( --md-sys-measurement-space50 );
	--md-comp-icon-button-default-space: var( --md-sys-measurement-space100 );
	--md-comp-icon-button-wide-space: var( --md-sys-measurement-space175 );
}

.ax-icon-button[data-width="narrow"] {
	--md-comp-icon-button-space: var( --md-comp-icon-button-narrow-space );
}
```

컨트롤은 한정자 없는 slot만 읽는다. 블록 어댑터가 쓰는 squareness 보간은 재현하지 않는다 —
그것은 size와 shape가 서로 다른 DOM 레벨에서 도착할 수 있어서 존재하고, React 컴포넌트는 둘
다 한 엘리먼트에서 받는다.

## 클래스와 파일 위치

클래스는 Axismundi 것, 토큰은 Material 어휘.

```text
components/material/     M3 Styles 축에 대응하는 primitive (icon, elevation)
components/<family>/     UI 컴포넌트 (buttons/button.js, buttons/icon-button.js)
styles/material/         primitive CSS        -> @layer axismundi.material
styles/components/       컴포넌트 CSS          -> @layer axismundi.components
styles/utilities/        공유 유틸리티          -> @layer axismundi.utilities
```

레이어 순서가 이미 이 구분을 담고 있다(`… material, components, patterns …`). UI 컴포넌트
CSS를 `material` 레이어에 두면 의미가 어긋난다.

## 상태와 접근성

**state layer는 content 역할을 container 위에 `color-mix`로 섞는다.** 테마가 자기 버튼을
칠하는 방식과 같고, container가 `transparent`인 스타일에서는 역할이 state opacity의 알파로
나온다 — 이것이 Standard icon button의 발행된 거동 그대로다. Standard는 **쉴 때 container가
없고 state layer가 상자를 그리는 유일한 순간**이지, 상태에서도 아무것도 안 그린다는 뜻이
아니다.

**포커스 링은 컴포넌트가 그린다.** 토큰은 테마에서 이미 온다.

```css
.ax-button:focus-visible {
	outline: var( --md-focus-ring-width ) solid var( --md-sys-color-secondary );
	outline-offset: var( --md-focus-ring-outward-offset );
}
```

**toggle은 고정 라벨 계약이다** — `aria-pressed`, 이름은 바뀌지 않음. 상세는
`axismundi-dialogs/docs/BUTTON-STATE.md`. 라벨을 교체하는 두 번째 계약과 한 컨트롤에서 섞지
않는다. `aria-pressed`는 이진값이므로 **세 상태를 순환하는 컨트롤에 쓰지 않는다**(테마스위처의
cycle 버튼이 선례).

선택 shape는 **방향이 아니라 교환**이다. round는 square가 되고 square는 round가 된다. 한쪽만
구현하면 square 토글에 선택 단서가 없다.

**이름이 보이지 않는 컨트롤은 실제 텍스트 span을 쓴다**(`.ax-sr-only`), `aria-label` 복제가
아니라. 문자열이 한 곳에만 있고, 브라우저 자동번역이 본문은 번역하고 속성은 놓치며, 보이는
이름과 접근 가능한 이름이 어긋나지 않는다(WCAG 2.5.3).

**잘못된 prop은 throw하지 않고 clamp하거나 거부한다.** 렌더 중 throw는 prop 오타 하나로
앱 전체를 내린다. 거부할 때는 `@wordpress/warning`으로 개발 중에 한 번 알린다 — 조용한 거부는
호출자가 왜 콜백이 안 오는지 모르게 만든다.

## 아이콘은 이름이 아니라 노드로 받는다

블록은 composition을 못 해서 아이콘을 attribute로 평탄화한다(`dialog-button`의 23개). React는
엘리먼트를 받고 CSS가 크기를 준다.

```jsx
<Button icon={ <Icon name="star" /> }>Label</Button>
```

슬롯이 `--md-icon-size`를 주고, 선택 시 `--md-icon-fill: 1`이 테마의 `@property` 등록 덕에
보간된다. 두 번째 아이콘 이름은 축을 움직일 수 없는 소스(정적 폰트, 아이콘 레지스트리)에서만
필요하고, 둘 다 아직 Social 소스가 아니다.

## 스타일북

M3가 Styles와 Components를 나누므로 페이지 계열도 둘이다.

```text
/social/stylebook/styles                    축 하나씩 검증 (color, typography, icons, elevation)
/social/stylebook/components/<name>         여러 축이 맞물린 컨트롤 검증
```

새 Components 페이지를 추가할 때 **네 군데를 같이 고친다**. 서버 rewrite를 빠뜨리면 URL이
WordPress 404가 된다.

```text
app.js                       라우트 정규식
pages/stylebook/index.js     dispatcher + nav
includes/route.php           rewrite 두 군데
axismundi.php                AXISMUNDI_CAPSTONE_REWRITE_VERSION 증가
```

공용 chrome은 `pages/stylebook/stylebook-page.css`의 `ax-stylebook-page__*`를 쓴다. Styles
페이지는 아직 자기 복사본을 들고 있고, 옮기는 일은 그 페이지 전체 rename이라 아직 안 했다.

## 검증

**재지 말고 추측하지 않는다**는 루트 AGENTS.md 규칙이 이 앱에서 구체적으로 어떤 모양인지.

- **펜이 숨겨져 있으면 `innerWidth/innerHeight`가 0이고 `requestAnimationFrame`이 안 돈다.**
  transition은 시작값에 얼어붙고 `elementFromPoint`는 전부 `null`이다. transition이 걸린 값을
  재려면 `* { transition: none !important }`를 임시로 넣고 **목표값**을 읽는다. hit-test는
  보이는 펜에서만 가능하다.
- **CSSOM 스캔은 두 번 잘못된 0을 줬다.** 규칙이 들어갔는지 확인할 때는 `build/frontend.css`를
  직접 grep한다. `var()`가 든 단축 속성은 CSSOM에서 longhand로 쪼개진다.
- **서버가 옛 번들을 주고 있을 수 있다.** 측정 전에 `?ver=` 해시가 `build/frontend.asset.php`의
  것과 같은지 본다.
- **새 검사는 깨뜨려서 결박을 확인한다.** 고친 것을 되돌렸을 때 예측한 그대로 실패하는지
  본 뒤에만 통과를 보고한다.

## 지금 비어 있는 것

구현 때 다시 보라고 남긴다. 추측으로 채우지 말 것.

- `Tooltip` primitive — M3는 web에서 아이콘 버튼에 툴팁을 요구한다. `title`로 흉내내지 않는다.
- `IconLink` — 아이콘이 이동시키면 링크다. `href` prop으로 버튼에 섞지 않는다.
- elevated 버튼의 hover elevation `1 -> 2` — 발행 문서 셋 중 어느 것도 직접 지지하지 않는
  추론 상태.
- `_data/icon_button.yml`의 shape 쌍 외 나머지 값 — 아직 validator가 없다.

## 관련 기록

- `DECISION-FRONTEND-ELEVATION-OWNERSHIP.md`
- `DECISION-FRONTEND-THEME-ASSET-CONTRACT.md`
- `REFERENCE-M3-TYPOGRAPHY.md` — typescale preset을 아직 만들지 않는 이유
- `axismundi-dialogs/docs/BUTTON-STATE.md` — toggle 상태와 이름 계약
- `products/styleguide/AGENTS.md` — 그 디렉터리를 고칠 때의 게이트
