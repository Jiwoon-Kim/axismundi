# 다음 작업 기록: Admin Layout, Sidebar, Icon Registry

## 상태

다음 구현 세션을 위한 조사·리팩터링 계획이다. 2026-09-30.

이 문서는 현재 Admin shell이 최종 구조라는 뜻이 아니다. 현재 구현은
Frontend/Admin 분리와 Design workspace의 경계를 검증하기 위한 첫 단계다.
다음 단계에서는 Site Editor의 외형이 아니라, route가 영역을 선언하고 layout이
영역을 조립하는 구조를 Axismundi 소유 코드로 만든다.

## 현재 기준점

현재 `main`의 Admin은 다음 두 workspace를 가진다.

```text
Operations
|- Overview
|- Settings
|- Diagnostics
`- Design >

Design
|- Styles
|- Templates >
|- Template Parts >
|- Patterns >
`- Components >
```

관련 구현은 다음 위치에 있다.

- `src/apps/admin/app.js`
- `src/apps/admin/components/site-editor/`
- `src/apps/admin/styles/app.css`
- `src/apps/admin/styles/sidebar-navigation-compat.css`
- `includes/assets.php`

Design의 preview iframe은 실제 `/social/` 문서를 읽는다. 따라서 preview 안의
M3 token과 stylesheet는 Frontend의 것이며, Admin 자체는 WPDS와 wp-admin host
boundary를 계속 소유한다.

## 조사 레퍼런스

다음은 비교와 포트 판단에 사용하는 로컬 Gutenberg source다.

- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/layout/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/site-editor-routes/`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-navigation-screen-main/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-navigation-item/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-navigation-screen/index.jsx`

이미 수행한 sidebar 포트 범위와 원본 경로는
`docs/DECISION-ADMIN-SIDEBAR-PORT.md`에 별도로 기록되어 있다.

## 핵심 관찰

### 1. Layout의 책임과 route의 책임이 현재 섞여 있다

Gutenberg Site Editor에서는 route가 sidebar, content, preview, mobile area 같은
영역을 선언하고 layout은 그것을 배치한다. 반면 현재 `AdminApp`은 route를 읽은 뒤
sidebar, global header, preview, inspector를 한 컴포넌트 안에서 조건문으로 직접
조립한다.

현재 shell에는 prototype 단계에서 유용한 공통 header와 Inspector가 있지만, 이들이
모든 future route의 불변 영역이라는 결정은 내리지 않았다. Styles, template browser,
component catalogue, diagnostics는 필요한 toolbar와 detail 영역이 서로 다르다.

### 2. 현재 drilldown chevron은 아직 정보 구조와 일치하지 않는다

`Templates`, `Template Parts`, `Patterns`, `Components`에는 trailing chevron이
있지만, 클릭 후 sidebar screen 자체는 교체되지 않는다. 실제 nested screen과 back
navigation이 없으면 chevron은 행동을 약속하지만 이행하지 않는 표시가 된다.

다음 중 하나를 명시적으로 선택해야 한다.

- nested sidebar screen과 back/focus restoration을 구현한다.
- 구현 전까지 해당 chevron을 제거하고 일반 route navigation으로 보인다.

현재 목표는 첫 번째다. 단, UI 장식보다 keyboard focus restoration을 먼저 구현한다.

### 3. Site Editor의 CSS와 class 이름은 소유권을 대체하지 않는다

현재 `edit-site-*` class와 scoped compatibility stylesheet는 빠른 비교와
composition 검증을 위해 존재한다. 그러나 Axismundi가 edit-site private DOM contract를
소유한다는 뜻이 되어서는 안 된다.

다음 리팩터링에서는 Site Editor 원본 CSS를 항목별로 분류한다.

- 공개 WordPress component의 contract에 필요한 CSS
- Axismundi layout metric으로 채택할 CSS
- Site Editor navigation state가 있어야만 의미가 생기는 CSS
- 단순한 visual artifact 또는 중복 CSS

분류 이후 Axismundi 소유 class namespace로 옮길 범위와, public component class를
그대로 둘 범위를 결정한다. class 이름만 바꾸는 기계적 rename은 하지 않는다.

### 4. 항상 표시되는 `Saved`는 실제 상태가 아니다

현재 sidebar footer의 `Saved`는 save subsystem과 연결되지 않은 placeholder다.
template, pattern, style registry가 편집 가능한 entity가 되기 전에는 제거하거나
명확한 placeholder가 아닌 상태로 숨긴다. 추후에는 dirty, saving, saved, error를
실제 편집 state와 연결한다.

### 5. URL과 i18n은 shell contract의 일부다

현재 dashboard URL과 일부 label은 literal string이다. 다음 작업에서:

- PHP bootstrap config에 `adminUrl: admin_url()`을 제공한다.
- sidebar item은 실제 `href`를 가진 link가 기본이 되도록 한다.
- SPA enhancement는 일반 좌클릭만 intercept하고, Ctrl/Cmd-click, middle click,
  새 탭 열기, 링크 복사는 브라우저 기본 동작으로 남긴다.
- UI label과 aria label은 `@wordpress/i18n`을 사용한다.

## 목표 구조

다음 구조는 폴더명 확정안이 아니라 책임 분리의 목표다.

```text
src/apps/admin/
|- app.js
|- layout/
|  |- admin-layout.js
|  `- route-areas.js
|- routes/
|  |- operations/
|  `- design/
|- sidebar/
|  |- navigation-provider/
|  |- navigation-screen/
|  `- navigation-item/
|- preview/
`- components/
```

첫 번째 구현은 Gutenberg의 모든 area를 재현하지 않는다. MVP route contract는
`sidebar`, `content`, `preview` 세 영역으로 제한한다. `inspector`, mobile area,
세부 width policy는 후속 확장으로 허용하되 지금 구현하지 않는다.

각 route는 최소한 다음 선언을 제공하는 방향을 검토한다. area 값은 고정 React
element로 제한하지 않고, route context를 받는 resolver function도 허용한다.

```js
{
	path: '/design/styles',
	areas: {
		sidebar: ( context ) => <StylesSidebar { ...context } />,
		content: <StylesPanel />,
		preview: ( context ) => <FrontendPreview { ...context } />,
	},
}
```

`AdminLayout`은 route의 도메인 의미를 알지 않고 area만 배치한다. route가 실제
sidebar, toolbar/content, preview를 소유한다. 이는 Gutenberg의 private
router/store를 복제하지 않고도 Site Editor의 composition principle을 채택하는 방법이다.

## Route state와 sidebar state의 분리

URL route state와 sidebar interaction state는 관련되지만 같은 상태가 아니다.

```text
route/history state
  현재 resource와 화면: /design/templates

sidebar navigation state
  forward/back direction, focus return target, transition lifecycle
```

브라우저 history의 back/forward와 sidebar animation 방향은 항상 같은 의미가 아니다.
따라서 route parsing과 `pushState`는 router/history layer에 두고, nested screen의
direction과 focus restoration은 별도 sidebar navigation provider가 소유한다.

## Sidebar navigation의 다음 구현 범위

### 필요한 행동

- forward/back navigation direction state
- nested screen 전환
- 새 screen의 첫 적절한 control, 보통 Back button으로 focus 이동
- Back 후 이전 drilldown trigger로 focus 복원
- reduced-motion을 고려한 transition
- link 기반 navigation과 SPA enhancement

SPA enhancement는 plain left click만 intercept한다. 다음 조건 중 하나라도 참이면
브라우저의 link 기본 동작을 보존한다.

```js
if (
	event.button !== 0 ||
	event.metaKey ||
	event.ctrlKey ||
	event.shiftKey ||
	event.altKey ||
	event.defaultPrevented
) {
	return;
}
```

### 아직 가져오지 않을 것

- `@wordpress/edit-site` private APIs
- `unlock()` 기반 router/store access
- edit-site theme preview state
- Gutenberg 자체 animation class를 이름만 복사하는 방식

Axismundi navigation state는 작은 일반 React provider로 구현한다. 먼저 behavior와
accessibility test를 만들고, transition은 그 다음에 붙인다.

## Icon Registry 조사 항목

Axismundi는 장기적으로 Core icon, Material Symbols SVG, Axismundi 고유 SVG를 같은
registry identifier로 소비하는 방식을 검토한다.

```text
core/*
material-symbols/*
axismundi/*
third-party/*
```

다만 이 부분은 아직 구현 결정이 아니다. 다음을 실제 설치된 WordPress와 Gutenberg
source에서 검증해야 한다.

- WordPress icon registry의 PHP registration API와 availability
- icon collection과 icon REST endpoint의 실제 route 및 권한
- identifier 안정성, collection ownership, collection slug uniqueness, registration
  conflict 처리 정책
- `core/icon` block이 registry를 읽는 실제 data contract
- REST payload의 SVG sanitization, cache, invalidation 책임
- wp-admin과 `/social/`에서 registry client를 사용할 때의 인증/성능 조건
- 기존 `@wordpress/icons`와 registry asset의 version/visual 차이를 처리하는 정책

검증이 끝나기 전에는 registry endpoint나 API 이름을 코드에 가정하지 않는다.

### 소유권 원칙

검증 후 registry를 채택한다면 다음 경계를 유지한다.

```text
shared/icons/
  registry client, schema, cache, resolver

apps/admin/components/registry-icon/
  WPDS/admin context rendering

apps/frontend/components/.../registry-icon/
  M3 context rendering
```

registry data client는 shared infrastructure다. React `Icon` component는 visual
surface가 소유한다. Frontend와 Admin이 하나의 visual icon component를 공유하지
않는다.

generic UI chrome인 back, close, chevron, more와 domain/resource icon인 actor,
object, federation, geo, calendar의 source policy도 registry 조사 결과와 WPDS/M3
guidance를 보고 정한다.

현재 `src/apps/admin/components/site-editor/sidebar-icons.js`의 local SVG는 temporary
compatibility asset이다. icon registry 또는 aligned `@wordpress/icons` version을
채택한 뒤에는 유지 여부를 다시 결정한다.

## 내일의 권장 순서

1. Gutenberg `layout`, route registry, `SidebarContent`, navigation state를 파일 단위로
   읽고, Axismundi에 필요한 contract와 불필요한 private coupling을 한 페이지 표로
   기록한다.
2. 현재 `app.js`에서 route metadata만 독립 route definition으로 이동한다. 이 단계에서는
   UI를 다시 그리지 않는다.
3. 세 영역만 가진 `route -> areas -> layout` contract를 도입하고, global
   header/Inspector를 route owned area로 전환한다.
4. 기존 화면이 시각적으로 거의 변하지 않는 상태까지 localhost에서 맞춘다.
5. 실제 nested Design sidebar와 focus restoration을 구현한다. 완료 전에는 구현되지
   않은 drilldown chevron을 표시하지 않는다.
6. fake `Saved` footer, hardcoded admin URL, literal navigation labels를 정리한다.
7. icon registry는 별도 조사 스파이크로 진행한다. registry API가 확인된 뒤에만
   `@wordpress/icons` direct import를 대체한다.
8. 각 단계에서 localhost의 Site Editor와 Axismundi를 desktop/mobile viewport에서
   비교하고, DOM, keyboard navigation, focus 순서를 검증한다.

## 완료 기준

- route가 area를 선언하고 layout이 area를 배치한다.
- route가 필요하지 않은 global header/inspector를 강제하지 않는다.
- visible drilldown chevron마다 실제 nested screen, back action, focus restoration이
  존재한다.
- navigation은 실제 URL을 제공하며 browser link behavior를 보존한다.
- browser back/forward 후 route, active sidebar item, focus target이 일관된다.
- page reload 후 현재 `p` route가 같은 screen으로 복원된다.
- 저장 상태 표시는 실제 state와 연결되거나 존재하지 않는다.
- Admin visual ownership과 Frontend visual ownership이 섞이지 않는다.
- icon registry 채택 여부와 API contract는 검증 기록 없이 가정하지 않는다.
- 구조 변경마다 한국어 판단 기록, 원본 레퍼런스, 검증 결과를 남긴다.
