# 계획 기록: Social Frontend Navigation Components

## 상태

**아이디어 기록.** 2026-10-03.

> 2026-10-04 갱신: 아이디어 3과 4는 판정됐다 —
> `DECISION-FRONTEND-NAVIGATION-COMPONENT-SLICING.md`. 출하 단위는 bar + rail을 함께
> 포함하고, route 자동생성은 붙이지 않으며 컴포넌트가 명시 `destinations` 배열을 받는다.
> 그 문서가 두 아이디어의 **어느 내부 구조도 채택하지 않았다**는 점은 유지된다. 아이디어
> 1·2는 여전히 미채택이다.

구현에 들어갈 때 측정하면서 바뀔 것을 전제로 적는다. 여기 있는 어떤 항목도 결정이 아니며,
결정이 되면 `DECISION-FRONTEND-NAVIGATION-MODEL.md`를 고치거나 별도 결정 기록을 만든다.

navigation **data**와 URL 거동은 이미 결정돼 있다(`DECISION-FRONTEND-NAVIGATION-MODEL.md`):
normalized navigation model, code-defined route registry, link-first SPA. 이 문서는 그 결정이
다루지 않은 **component 내부 구조**만 다룬다.

## 먼저: M3 Expressive가 baseline을 폐기했다

`SOURCE-M3-NAVIGATION-RAW.md`에 1차 자료가 있다. 지금 기록해 두지 않으면 옛 스펙으로
구현하게 되므로 먼저 적는다.

```text
baseline navigation bar    no longer recommended
baseline navigation rail   no longer recommended

flexible navigation bar    더 낮고, medium window에서 horizontal navigation item 지원
collapsed navigation rail  baseline rail을 대체
expanded navigation rail   navigation DRAWER를 대체 (modal / non-modal)

active label 색            on-surface-variant -> secondary  (bar·rail 공통)
navigation bar elevation   그림자 없음   ← 틀렸다. 아래 반증 참조
active indicator           pill shape (M2의 icon weight/fill 방식이 아님)
```

> **반증 (2026-10-04, 추출된 token table).** 위 "navigation bar elevation 그림자 없음"은
> 틀렸다. flexible bar는 elevation과 그림자 색을 모두 발행한다:
>
> ```txt
> md.comp.nav-bar.container.elevation     -> md.sys.elevation.level2
> md.comp.nav-bar.container.shadow-color  -> md.sys.color.shadow   (폐기되지 않음)
> md.comp.nav-rail.collapsed.container.elevation -> md.sys.elevation.level0
> ```
>
> level0인 쪽은 **rail**이다. bar의 사실로 적힌 것이 rail의 사실이었다. 같은 블록의 색
> 전사는 맞다 — bar·rail 둘 다 active label이 `md.sys.color.secondary`, inactive가
> `on-surface-variant`다. 값은 `SOURCE-M3-NAVIGATION-RAW.md`의 token table 절에 있다.

window size class 담당 범위가 **medium에서 겹친다**:

```text
navigation bar    compact · medium
navigation rail   medium · expanded · large · extra-large
용량              bar 3-5 destination / rail 3-7 destination + optional FAB
```

## 아이디어 1 — 컴포넌트 둘로 등록하고 유틸리티에서 합친다

컴포넌트를 둘로 두는 것은 이미 결정돼 있다. 새로운 부분은 **고르는 일을 누가 하느냐**다.

M3가 이 유틸리티를 요구한다. bar와 rail의 담당 구간이 medium에서 겹치므로, "어느 쪽을
쓰는가"는 breakpoint 하나로 결정되지 않고 destination 수·FAB 유무·화면 성격이 함께
들어가는 판단이다. 그 판단이 각 페이지에 흩어지면 같은 앱에서 화면마다 다른 답이 나온다 —
M3가 "Always put the rail in the same place, even on different screens" 라고 못박은 바로 그
실패다.

```text
navigation model (결정됨)
  -> useNavigationSurface()   window size class + destination 수 -> bar | rail | rail(expanded)
      -> <NavigationBar  items={…} />
      -> <NavigationRail items={…} />
```

### 이름은 Button 결정을 따른다

기존 결정 기록이 `md-navigation-rail` / `md-navigation-bar`로 쓰고 있는데, 이는 Button 이전의
표기다. 현재 규약은:

```text
컴포넌트   components/navigation/navigation-bar.js · navigation-rail.js
클래스     .ax-navigation-bar · .ax-navigation-rail
토큰       --md-comp-navigation-bar-* · --md-comp-navigation-rail-*
```

클래스는 Axismundi 것, 토큰은 Material 어휘. 이 문서가 채택될 때 기존 기록의 `md-` 표기도
같이 고친다.

## 아이디어 2 — navigation item을 원자화한다

M3가 실제로 item을 독립 축으로 쓴다. flexible navigation bar는 **medium window에서
horizontal navigation item**을 가지므로, item 내부 배치는 container와 별개로 바뀐다.

이 축에는 이 저장소에 이미 이름이 있다. 블록 테마 쪽에서 `axismundi-navigation-icons`가 item
layout(아이콘 옆/위)을 style variation으로 소유하고, M3 item baseline(pill·state·indicator)은
테마가 소유한다. React 앱은 그 플러그인을 상속하지 않지만 — 다른 표면이다 — **축 이름은
일치시켜야** 두 표면이 같은 어휘로 말한다.

원자 후보, 그리고 Button에서 확인된 규칙을 적용한 형태:

```text
NavigationItem          <a> 또는 <button>, 상태는 여기 하나
  ActiveIndicator       pill. 배경이 아니라 별도 레이어 (Elevation과 같은 꼴)
  Icon slot             Icon primitive 그대로. 선택 시 FILL 0 -> 1
  Label                 typescale slot
  Badge                 optional
```

확인할 것 두 가지. `ActiveIndicator`를 레이어로 둘지 item의 배경으로 둘지는 shape morph와
state layer가 같이 걸리므로 측정이 필요하다. 그리고 선택 상태의 접근성 계약은
`axismundi-dialogs/docs/BUTTON-STATE.md`의 고정 라벨 toggle과 **다르다** — navigation item은
toggle이 아니라 현재 위치를 가리키므로 `aria-pressed`가 아니라 `aria-current="page"`다.

## 아이디어 3 — horizontal / vertical로 두 컴포넌트를 전환한다

여기서 **축이 둘로 갈린다**. 기록해 두는 이유가 그것이다.

```text
core/navigation 의 orientation   container 방향
M3 의 horizontal/vertical item   item 안에서 icon과 label의 배치
```

bar와 rail은 container 방향이 다르지만, 방향 하나로 서로 전환되지는 않는다. rail은
collapsed / expanded 라는 별도 축을 더 갖고, expanded는 modal / non-modal까지 갖는다. 그래서
"orientation prop 하나로 두 컴포넌트를 전환"은 M3와 1:1로 맞지 않는다.

한편 **item 쪽 horizontal/vertical은 실재하는 축**이고, 그쪽이 core/navigation의 orientation
보다 M3 어휘에 가깝다. 어느 이름을 어느 축에 쓸지는 구현 때 정한다. 지금 정하면 두 축 중
하나가 잘못된 이름을 갖게 될 가능성이 높다.

## 아이디어 4 — core/page-list 같은 route 자동 목록 유틸리티

**측정된 선행 조건이 하나 있다.** 현재 `src/apps/frontend/app.js`의 `getFrontendRoute()`는
if 문과 정규식의 연쇄다. 경로가 제어 흐름에 인코딩돼 있어서 **열거할 수 없다**:

```js
if ( 'stylebook' === relativePath ) { … }
if ( 'stylebook/styles' === relativePath ) { … }
const m = relativePath.match( /^stylebook\/components\/(buttons)$/ ); …
```

그래서 자동 목록 생성은 route table이 먼저 있어야 한다. 그 table은 이미 제안돼 있고
(`DECISION-FRONTEND-NAVIGATION-MODEL.md`의 `socialRoutes`) 아직 구현되지 않았다. 즉 이
아이디어의 실질은 "page-list를 만들자"가 아니라 **"route registry를 실제로 만들고 라우터가
그것을 읽게 하자"**이고, 그건 이미 결정된 항목이다.

core/page-list와 다른 점도 적어 둔다. page-list는 **데이터**(WP pages)를 나열한다. 우리
route는 **코드**다. 그래서 자동 생성의 대상은 "사이트에 있는 글"이 아니라 "앱이 가진 화면"
이고, registry 하나면 충분하다 — 그리고 그 registry가 곧 navigation model의 source라는 것이
기존 결정의 결론과 같다.

주의할 것 하나: registry를 그대로 navigation에 흘리면 `stylebook/*` 같은 개발 전용 경로가
사용자 내비게이션에 올라온다. route record에 노출 여부 표시가 필요하고, 그 표시는 권한이나
feature flag와는 다른 축이다.

## 지금 하지 않는 것

- navigation component 구현
- route registry 구현 및 라우터 교체
- `md-` 표기 일괄 변경
- `axismundi-navigation-icons`와의 공유 코드
- Specs / Guidelines / Accessibility 탭 캡처 (구현 착수 시 `SOURCE-M3-NAVIGATION-RAW.md`에 추가)

## 관련 기록

- `DECISION-FRONTEND-NAVIGATION-COMPONENT-SLICING.md` — 아이디어 3·4의 판정, 출하 순서
- `DECISION-FRONTEND-NAVIGATION-MODEL.md` — navigation data·URL 거동 (채택됨)
- `SOURCE-M3-NAVIGATION-RAW.md` — 1차 자료
- `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md` — window size class
- `axismundi-dialogs/docs/BUTTON-STATE.md` — 선택 상태 계약 (navigation과는 다름)
