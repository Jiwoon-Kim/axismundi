# 결정 기록: Social Frontend Navigation Model

## 상태

채택됨. 2026-10-01.

이 문서는 Social Frontend의 navigation data, navigation component, browser URL behavior를
분리한다. React router implementation, WordPress menu persistence, Admin navigation editor는
아직 구현하지 않는다.

## 문제

Social의 Navigation Rail과 compact Navigation Bar는 application information architecture를
표현한다. 링크와 label을 각각의 React component JSX에 직접 작성하면, 이후 menu source,
권한, feature availability, user preference를 도입할 때 component implementation과 data를
함께 바꾸게 된다.

반대로 WordPress block Navigation이나 theme navigation markup을 `/social/` runtime에 직접
삽입하면, Frontend가 block theme presentation/runtime에 다시 결합된다.

## 결정

Navigation data와 navigation rendering component를 분리한다.

```text
navigation source
  -> normalized navigation model
      -> md-navigation-rail
      -> md-navigation-bar
      -> other navigation presentation
```

초기 navigation source는 code-defined default route registry다. 이것은 최종 persistence
format이 아니라 Frontend product를 만들기 위한 temporary provider다.

```js
export const socialRoutes = [
  {
    id: 'home',
    label: 'Home',
    icon: 'home',
    path: '/social/',
  },
  {
    id: 'notifications',
    label: 'Notifications',
    icon: 'notifications',
    path: '/social/notifications/',
  },
];
```

Navigation Rail/Bar는 registry를 import하거나 normalized item array를 props로 받는다. 각
component가 independent hard-coded link list를 소유하지 않는다.

```jsx
<NavigationRail items={ socialRoutes } />
<NavigationBar items={ socialRoutes } />
```

## Link-first SPA behavior

React application도 실제 anchor URL을 보존한다.

```html
<a href="/social/notifications/">Notifications</a>
```

SPA router는 unmodified primary click에만 History API navigation을 적용한다.

```text
ordinary primary click on an internal route
  -> prevent default
  -> application route transition

middle click, Ctrl/Cmd click, Shift click, target/download/external link
  -> browser default behavior
```

이 contract는 deep link, reload, bookmark, open-in-new-tab, browser navigation을 보존한다.
React SPA라는 이유로 inert element 또는 `onClick` only navigation을 사용하지 않는다.

## Route and navigation scope

Initial route records represent application destinations, not WordPress block navigation nodes.

```text
Social route registry
  -> Frontend route identity, label, href, icon metadata

Navigation component
  -> current-route presentation and interaction

Router
  -> location parsing, route selection, internal-link enhancement
```

`md-navigation-rail`과 `md-navigation-bar`는 the same navigation model을 각 responsive
layout에 맞춰 표현한다. compact/expanded differences는 component/layout concern이며 menu
data duplication의 이유가 아니다.

## Future provider boundary

나중에 site/operator-owned navigation, user-specific navigation, WordPress menu entity를
지원해야 할 수 있다. 이 경우 source provider만 교체하거나 merge한다.

```text
default route registry
  + site policy/menu provider
  + user preference/visibility provider
  -> normalized navigation model
  -> React navigation components
```

가능한 WordPress integration은 normalized model의 provider가 REST/custom endpoint를 통해
menu/entity data를 읽는 방식이다. Social components는 WordPress menu serialization,
block navigation markup, or `wp_nav_menu()` output을 직접 소비하지 않는다.

Admin navigation editor와 persistence는 실제 Social route/component requirements가 확정된
뒤 별도 checkpoint로 설계한다.

## Excluded work

- WordPress menu REST/custom endpoint
- navigation registry persistence
- Admin drag/drop navigation editor
- role/capability-specific navigation filtering
- user custom ordering/visibility storage
- React router library selection or implementation
- Gutenberg Navigation block reuse

## Related decisions

- `PLAN-FRONTEND-NAVIGATION-COMPONENTS.md` — component 내부 구조 아이디어 (미채택).
  M3 Expressive가 baseline bar/rail을 폐기했고 expanded rail이 drawer를 대체한다는 점,
  그리고 이 문서의 `md-navigation-*` 표기가 현재 컴포넌트 규약보다 앞선 표기라는 점이
  거기 적혀 있다.
- `DECISION-FRONTEND-PRODUCT-FIRST.md`
- `DECISION-FRONTEND-THEME-ASSET-CONTRACT.md`
- `RESEARCH-ADMIN-SIDEBAR-NAVIGATION.md`
