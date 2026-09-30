# 조사 기록: Gutenberg Site Editor의 Route, Layout, Sidebar 책임

## 상태

2026-09-30 조사. Axismundi Admin의 첫 route-area 리팩터링에 사용하는 근거 기록이다.

## 조사한 원본

- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/layout/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/site-editor-routes/styles.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/site-editor-routes/templates.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar/index.jsx`

## 원본에서 확인한 책임 분리

| Gutenberg 요소 | 실제 책임 | Axismundi의 채택 여부 |
| --- | --- | --- |
| route object | 현재 URL에 필요한 `areas`, 선택적 `widths`를 선언 | 채택: MVP는 `sidebar`, `content`, `preview`만 |
| route area value | 고정 React element 또는 route context를 받는 function/async function | 채택: element와 resolver function 허용 |
| `Layout` | route가 제공한 area를 desktop/mobile/canvas에 배치 | 채택: route 도메인을 모르는 `AdminLayout` |
| `SidebarNavigationProvider` | forward/back direction과 focus return selector만 보관 | 채택: 별도 Axismundi state로 구현 예정 |
| `SidebarContentWrapper` | route 교체 뒤 focus 이동과 optional animation class 부여 | 채택 예정: 실제 nested screen 도입 시 구현 |
| `SaveHub`, `SavePanel`, editor canvas | Gutenberg editor entity, save, preview lifecycle | 미채택: Axismundi entity editor가 생길 때 별도 설계 |
| private router/store | `edit-site` 내부 route, settings, theme preview state | 미채택: Axismundi는 자체 URL parser/history 사용 |

## 중요한 원칙

### Route state와 sidebar state는 다르다

Gutenberg도 route location과 `SidebarNavigationProvider`를 별도로 둔다.

```text
route/history
  어떤 resource와 application route를 보고 있는가

sidebar navigation
  forward/back direction, focus return target, transition lifecycle
```

Axismundi도 이 분리를 유지한다. browser history의 back/forward가 언제나 sidebar
animation direction을 뜻하지는 않는다.

### Area는 route가 소유하고 layout은 배치만 한다

`stylesRoute`는 고정 element와 resolver를 섞어 쓴다.

```jsx
areas: {
	content: <SidebarGlobalStyles />,
	sidebar: <SidebarNavigationScreenGlobalStyles />,
	preview: ( { siteData } ) => <StylesPreviewArea siteData={ siteData } />,
}
```

이것이 Axismundi가 채택할 핵심이다. `AdminLayout`은 Styles, Templates,
Diagnostics의 의미를 알지 않아야 한다. route가 sidebar, route-owned content,
preview를 제공한다.

### Sidebar transition은 state와 focus behavior가 있을 때만 의미가 있다

Gutenberg sidebar는 navigation direction과 focus selector를 갖고, 새 screen의 첫
tabbable control 또는 back 후 원 trigger에 focus를 둔다. `animation-duration`이나
`will-change`만 복사하는 것은 이 behavior를 제공하지 않는다.

따라서 Axismundi는 nested screen을 구현하기 전에는 Site Editor transition의 CSS
artifact를 기능처럼 취급하지 않는다.

## Axismundi MVP contract

첫 번째 contract는 의도적으로 작다.

```js
{
	path: '/design/styles',
	areas: {
		sidebar: ( context ) => <StylesSidebar { ...context } />,
		content: <StylesContent />,
		preview: ( context ) => <FrontendPreview { ...context } />,
	},
}
```

`content`는 본문 text 영역이 아니라 route가 소유하는 heading, toolbar, list/detail,
action UI를 포함하는 application area다.

MVP에서 Inspector, mobile 전용 area, widths resolver, editor canvas는 구현하지 않는다.
후속 route contract 확장 대상으로만 남긴다.

## 구현에 사용하지 않을 Gutenberg 세부사항

- `@wordpress/edit-site`와 `unlock()`
- private `@wordpress/router` hooks
- theme preview와 template editor canvas state
- `SaveHub`, `SavePanel`, unsaved-changes handling
- `SlotFillProvider`와 Gutenberg PluginArea
- Site Editor DOM class 이름을 Axismundi의 장기 public contract로 삼는 방식

## 다음 변경의 검증

- route metadata가 `app.js` 바깥에 존재한다.
- `AdminLayout`은 area만 배치하며 route별 조건문을 갖지 않는다.
- 현재 Operations와 Design의 시각 배치가 의도하지 않게 바뀌지 않는다.
- page reload와 browser back/forward가 같은 `p` route를 복원한다.
- `npm run build`와 `git diff --check`를 통과한다.
