# 조사 기록: Admin Sidebar Compatibility Ownership

## 상태

2026-09-30 조사. sidebar navigation checkpoint 뒤의 placeholder/compatibility cleanup을 위한 class ownership 분류다. Icon Registry와 `sidebar-icons.js` 교체는 이 기록의 범위 밖이다.

## 분류

| 항목 | 현재 출처 | Axismundi가 유지할 계약 | 처리 |
| --- | --- | --- | --- |
| `edit-site-sidebar__content` | 로컬 port가 직접 렌더 | Axismundi sidebar scroll region | `ax-admin-sidebar__content`로 이전 |
| `edit-site-sidebar__screen-wrapper` | 로컬 port가 직접 렌더 | screen mount, focus, transition wrapper | `ax-admin-sidebar__screen`으로 이전 |
| `edit-site-sidebar-button` | 로컬 `SidebarButton` | Axismundi admin sidebar button composition | `ax-admin-sidebar-button`으로 이전 |
| `edit-site-sidebar-navigation-item*` | 로컬 `SidebarNavigationItem` | Axismundi navigation item/chevron | `ax-admin-sidebar-navigation-item*`로 이전 |
| `edit-site-sidebar-navigation-screen*` | 로컬 `SidebarNavigationScreen` | Axismundi screen anatomy | `ax-admin-sidebar-screen*`으로 이전 |
| `components-item`, `components-button` | `@wordpress/components` runtime | WordPress public component styles | 유지 |
| `@wordpress/components`, `@wordpress/ui`, `@wordpress/icons`, `@wordpress/dom` | public package imports | WPDS/public implementation primitive | 유지 |
| `sidebar-icons.js` | temporary local icon asset | host icon-version compatibility | 유지; Icon Registry spike에서 재평가 |

## 판단

현재 `edit-site-*` class는 `@wordpress/edit-site`가 제공하거나 소비하는 public CSS API가 아니다. Axismundi가 local port markup과 stylesheet를 모두 소유하므로 장기 namespace로 남기면 ownership을 오해하게 만든다.

반대로 `components-*` class와 public `@wordpress/*` package는 Axismundi가 다시 구현하지 않는다. 이들은 WPDS/Admin integration boundary에 속한다.

## Fake Save State

Operations와 Design sidebar의 `Saved` footer는 실제 dirty/entity/save lifecycle과 연결되지 않았다. 기능이 없는 상태 문구는 제거한다. `SidebarNavigationScreen`의 optional footer API 자체는 이후 실제 save subsystem이 생길 수 있으므로 유지한다.

## 제외 범위

- `sidebar-icons.js`의 SVG path 대체
- Core Icon Registry, collection model, REST payload 조사
- navigation direction, focus return, history behavior 변경
- 실제 template/pattern/component registry

## 검증 기준

- Admin source와 runtime markup에서 local `edit-site-*` class가 남지 않는다.
- public WordPress component imports와 `components-*` class는 그대로 사용한다.
- `Saved` text가 두 sidebar에서 사라진다.
- Design root/resource drilldown, Back, keyboard focus restoration은 이전 checkpoint와 동일하게 동작한다.
