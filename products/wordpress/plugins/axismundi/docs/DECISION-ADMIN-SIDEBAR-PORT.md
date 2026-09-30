# 결정 기록: Admin Sidebar의 Gutenberg Site Editor 로컬 포트

## 상태

채택됨. 2026-09-30.

## 문제

Axismundi Admin의 Operations와 Design은 WordPress Site Editor와 같은 종류의
좌측 탐색 화면을 필요로 한다. 하지만 Site Editor의 sidebar composite는
`@wordpress/edit-site`가 플러그인 소비자를 위해 공개한 API가 아니다. 플러그인은
그 내부 컴포넌트를 직접 import하거나 Gutenberg의 private store/router에 결합해서는
안 된다.

따라서 목표는 Site Editor의 정보 구조, 접근성 구조, 공개 WordPress UI primitive를
재사용하면서도 Axismundi가 라우팅과 상태를 소유하는 것이다.

## 결정

다음 Site Editor 컴포넌트를 Axismundi 소스 트리에 로컬 포트한다.

- `SidebarButton`
- `SidebarNavigationItem`
- `SidebarNavigationScreen`

현재 포트 위치는 다음과 같다.

- `src/apps/admin/components/site-editor/sidebar-button/index.js`
- `src/apps/admin/components/site-editor/sidebar-navigation-item/index.js`
- `src/apps/admin/components/site-editor/sidebar-navigation-screen/index.js`

포트한 컴포넌트는 다음 공개 WordPress 패키지만 런타임 의존성으로 사용한다.

- `@wordpress/components`
- `@wordpress/ui`
- `@wordpress/icons`
- `@wordpress/i18n`

Axismundi의 route 상태와 history 전환은 `app.js`가 소유한다. Site Editor의
private router, edit-site store, sidebar context는 포트하지 않는다.

## 원본 레퍼런스

로컬 Gutenberg checkout의 원본은 아래 경로에 있다.

- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-button/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-navigation-item/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-navigation-screen/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-navigation-screen-main/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-button/style.scss`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-navigation-item/style.scss`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-navigation-screen/style.scss`

## 채택한 구조

`SidebarNavigationItem`은 Site Editor와 같은 공개 primitive 조합을 유지한다.

```jsx
<Item className="edit-site-sidebar-navigation-item">
	<Stack direction="row" align="center" justify="start" gap="sm">
		<Icon icon={ icon } size={ 24 } />
		<FlexBlock>{ children }</FlexBlock>
		<Icon className="edit-site-sidebar-navigation-item__drilldown-indicator" />
	</Stack>
</Item>
```

leading icon과 trailing drilldown icon은 별도 Axismundi 아이콘 컴포넌트가 아니다.
둘 다 `@wordpress/icons`의 `Icon`으로 렌더된다.

- leading icon: 화면 항목이 `icon` prop으로 전달한다.
- trailing icon: `withChevron`이 참일 때 `chevronRightSmall`을 사용하며 RTL에서는
  `chevronLeftSmall`을 사용한다.
- 상단 back/dashboard control: `@wordpress/components`의 `Button`을 감싼
  `SidebarButton`이다. `href`가 있으면 `<a>`, `onClick`만 있으면 `<button>`을
  출력한다.

Design 현재 항목의 icon provenance는 다음과 같다.

| Axismundi 항목 | 아이콘 export | 패키지 | drilldown |
| --- | --- | --- | --- |
| Styles | `styles` | `@wordpress/icons` | 아니오 |
| Templates | `layout` | `@wordpress/icons` | 예 |
| Template Parts | `layout` | `@wordpress/icons` | 예 |
| Patterns | `symbol` | `@wordpress/icons` | 예 |
| Components | `blockDefault` | `@wordpress/icons` | 예 |

## 의도적으로 포트하지 않은 것

다음은 Site Editor 내부 구현이며 Axismundi의 의존성이 아니다.

- `@wordpress/edit-site` private API와 `unlock()`
- `@wordpress/router`의 edit-site 전용 history/link/location 결합
- `editSiteStore`와 theme preview 상태
- `SidebarNavigationContext`의 애니메이션 방향과 focus 복원 정책
- dashboard URL을 Site Editor settings store에서 읽는 동작

대신 Axismundi는 URL의 `p` query parameter를 읽고, `history.pushState()`로
자신의 화면을 전환한다. 이는 플러그인 admin screen이 WordPress core 또는
Gutenberg 내부 store를 소유하지 않는다는 경계를 보장한다.

## CSS와 inline style의 경계

Site Editor를 시각적 정답으로 보되, 구현 관행 전체를 복제하지 않는다.

- `HStack`, `Stack`, `VStack` 같은 공개 WordPress layout primitive가 생성하는
  CSS custom property 기반 inline style은 허용한다. 해당 component API의 계약이다.
- Axismundi 고유 spacing, 색상, sticky 영역, scrollbar, responsive layout은
  `src/apps/admin/styles/sidebar.css`와 Admin stylesheet가
  소유한다.
- Gutenberg의 private store 상태를 표현하기 위한 임시 inline style이나 DOM
  override는 추가하지 않는다.
- `wp-admin` host 조정은 필요한 범위만 unlayered compatibility CSS에 둔다.

따라서 이 포트는 "Site Editor를 그대로 vendoring"하는 것이 아니라,
공개 UI primitive와 검증된 composition을 채택하는 것이다.

## 버전 정합성 주의

현재 실행 중인 local Gutenberg build의 header back chevron SVG는 설치된
`@wordpress/icons` export와 path가 다르다. 그래서
`src/apps/admin/components/site-editor/sidebar-icons.js`에 host build와 동일한
filled chevron을 compatibility asset으로 보관한다.

이 파일은 새로운 디자인 시스템이 아니다. WordPress/Gutenberg 의존성 버전을
맞추거나 host build가 변경될 때, 실제 Site Editor DOM과 비교한 뒤 제거 또는
갱신해야 한다.

## 검증 기준

변경 후에는 localhost의 두 화면을 실제로 비교한다.

- `http://localhost:8884/wp-admin/site-editor.php?p=%2F`
- `http://localhost:8884/wp-admin/admin.php?page=axismundi&p=%2Fdesign%2Fstyles`

최소 확인 항목은 다음과 같다.

- root sidebar control은 dashboard URL을 가진 `<a>`이다.
- Design sidebar의 back control은 `<button aria-label="Back">`이다.
- navigation item은 leading `Icon`을 출력한다.
- drilldown item은 `edit-site-sidebar-navigation-item__drilldown-indicator`를
  가진 trailing `Icon`을 출력한다.
- sidebar screen wrapper, content, item의 좌우 padding과 폭을 DevTools에서
  원본과 비교한다.
- `npm run build`와 `git diff --check`를 통과한다.

## 재검토 시점

다음 경우 이 기록과 원본을 다시 대조한다.

- WordPress 또는 Gutenberg의 `@wordpress/components`, `@wordpress/ui`,
  `@wordpress/icons`를 업그레이드할 때
- Admin sidebar에 transition, focus restoration, nested route가 추가될 때
- Site Editor와 다른 admin navigation model이 필요한 것이 확인될 때
