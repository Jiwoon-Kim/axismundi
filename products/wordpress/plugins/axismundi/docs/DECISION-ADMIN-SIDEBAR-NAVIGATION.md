# 결정: Admin Sidebar Navigation State

## 상태

2026-09-30 채택. 이 결정은 `cd494f1`의 route-area refactor 다음 단계로, Axismundi Admin sidebar의 실제 drilldown behavior와 접근성 계약을 정의한다.

## 근거

다음 로컬 Gutenberg source를 재검토했다.

- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-navigation-item/index.jsx`
- `C:/Users/thaum/dev/gutenberg/build/styles/edit-site/style.css`

Gutenberg Site Editor의 `SidebarNavigationProvider`는 URL router를 소유하지 않는다. forward/back direction과 focus return selector만 유지하고, `SidebarContentWrapper`가 screen 교체 시 첫 tabbable control 또는 이전 trigger에 focus를 둔다.

## 결정

Axismundi는 `src/apps/admin/sidebar/navigation/`에 작은 자체 provider를 둔다. 이 provider는 public `@wordpress/element`와 `@wordpress/dom`만 사용한다.

```text
route/history state
  URL, browser back/forward, resource route

sidebar navigation state
  current screen, direction, focus return selector stack
```

두 state는 연결되지만 같은 state가 아니다.

- 일반 sidebar link는 resource URL을 `pushState()`로 갱신하고 필요한 경우 forward screen을 지정한다.
- workspace root의 Back은 URL을 이전 workspace root로 갱신하고, forward trigger에 focus를 복원하는 back transition이다.
- child screen의 Back은 URL을 새로 쓰지 않고 sidebar screen만 root로 되돌린다.
- browser back/forward와 page reload는 route의 default screen으로 sidebar를 다시 동기화한다.
- forward screen은 첫 tabbable control, 현재 Design resource screen에서는 Back button에 focus한다.
- forward navigation은 trigger id를 LIFO focus stack에 추가한다.
- Back은 stack의 가장 최근 trigger에 focus를 복원한 뒤 그 entry를 pop한다. 따라서
  `Design -> Assets -> Icons`처럼 두 단계 이상 내려간 뒤에도 각 Back이 바로 앞 screen의
  trigger를 복원한다.

## Design Root

`/design`은 더 이상 `/design/styles`의 숨은 alias가 아니다. Design root screen과 root preview를 갖는 workspace landing route다.

```text
/design
  Design root sidebar screen

/design/styles
/design/templates
/design/template-parts
/design/patterns
/design/components
  해당 resource sidebar screen
```

이로써 `/design`과 `/design/styles`는 별도 presentation state를 갖는다. route canonicalization이 필요하지 않다.

Operations root에서 Design root로 들어갈 때와 Design root에서 Operations root로 돌아갈 때는 URL과 focus를 모두 전환한다.

```text
Operations Design trigger
  forward + pushState( /design )
    -> Design root Back button focus

Design root Back
  back + pushState( / )
    -> Operations Design trigger focus
```

## Chevron Rule

trailing chevron은 실제 sidebar screen 전환을 만드는 Design resource에만 표시한다. `Styles`는 forward screen으로 전환될 수 있지만 Site Editor와 같이 root navigation에서 direct item으로 보이므로 chevron을 표시하지 않는다.

## Animation

animation은 state transition이 존재할 때만 붙는다. `slide-from-left/right` class는 provider의 `direction`에서 나오며, Axismundi scoped keyframe을 사용한다. reduced-motion 환경에서는 기존 compatibility stylesheet 정책에 따라 duration을 0으로 만든다.

## 제외 범위

- template/pattern/component registry의 실제 resource 목록
- icon registry spike와 `sidebar-icons.js` 대체
- mobile 전용 route areas

## 검증

- `npm run build`
- `git diff --check`
- `/design`에서 actual drilldown chevron이 필요한 item에만 노출됨
- link click 및 keyboard Enter로 child screen 진입 시 Back button으로 focus 이동
- Back button click 및 Enter 후 원 trigger로 focus 복원
- browser back/forward가 URL에 대응하는 Design screen을 복원
