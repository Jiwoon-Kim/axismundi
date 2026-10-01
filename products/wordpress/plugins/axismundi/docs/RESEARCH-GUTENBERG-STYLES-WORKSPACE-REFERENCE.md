# 조사: Gutenberg Styles Workspace와 Axismundi Frontend Stylebook

## 상태

조사 기록. 2026-09-30.

이 문서는 Gutenberg Site Editor의 Styles workspace와 Style Book의 실제 소스 경로를
기록하고, Axismundi가 참고할 UI/ownership 경계를 정리한다. 이 문서는 Gutenberg
Global Styles runtime을 Axismundi에 포트하자는 결정이나 `style.json` schema의 명세가
아니다. Product-first implementation direction은
`DECISION-FRONTEND-PRODUCT-FIRST.md`를 따른다.

## 배경

Axismundi에는 두 개의 독립된 presentation surface가 있다.

```text
WordPress block theme
  -> theme.json, Global Styles, block/editor presentation

Social frontend
  -> React application, Material foundation tokens, production components
```

Social route는 theme foundation assets를 직접 요청한다. 따라서 Social의 Stylebook은
Site Editor preview environment를 재구성하는 화면이 아니라, 실제 Frontend runtime 안에서
production component fixture를 렌더하는 route여야 한다.

```text
Admin preview iframe
  -> /social/ Frontend runtime
      -> theme foundation CSS
      -> Frontend global CSS
      -> production component fixture
```

Admin document 자체는 Frontend/M3 presentation CSS를 import하지 않는다.

## Gutenberg source map

### Styles route and three areas

`packages/edit-site/src/components/site-editor-routes/styles.jsx`

Site Editor의 `stylesRoute`는 다음 세 area를 route가 직접 제공한다.

```jsx
{
  path: '/styles',
  areas: {
    content: <SidebarGlobalStyles />,
    sidebar: <SidebarNavigationScreenGlobalStyles />,
    preview: ( { siteData } ) => <StylesPreviewArea siteData={ siteData } />,
  },
}
```

`preview=stylebook`일 때 preview area는 `StyleBookPreview`를, 그렇지 않을 때는 template
editor canvas를 사용한다. 이는 Axismundi가 채택한 `sidebar | content | preview` layout
분리의 직접적인 선례다.

### Styles page shell

`packages/edit-site/src/components/sidebar-global-styles/index.jsx`

이 파일은 `Page` shell, title, Style Book action, Global Styles action menu, 그리고
`section` query state를 조합한다. Axismundi가 참고할 것은 page-level heading/action과
`section`/`preview`를 분리하는 URL model이다.

### Styles navigator and root screen

```text
packages/global-styles-ui/src/global-styles-ui.tsx
packages/global-styles-ui/src/screen-root.tsx
packages/global-styles-ui/src/root-menu.tsx
```

`GlobalStylesUI`는 internal Navigator와 다음 screen을 소유한다.

```text
/
/colors
/colors/palette
/typography
/layout
/background
/shadows
/blocks
```

`ScreenRoot`는 preview tile과 root menu를 렌더하고, `RootMenu`는 Typography, Colors,
Background, Shadows, Layout 항목을 제공한다.

### Gutenberg Style Book runtime

```text
packages/editor/src/components/style-book/index.jsx
packages/editor/src/components/style-book/examples.tsx
packages/editor/src/components/style-book/categories.ts
packages/editor/src/components/style-book/constants.ts
packages/edit-site/src/components/site-editor-routes/stylebook.jsx
```

이 runtime은 `BlockEditorProvider`, Global Styles merge/output, block examples, iframe
renderer를 결합한다. 이는 Gutenberg block/editor preview의 구현이며 Axismundi Social
Stylebook에 그대로 포트하지 않는다.

## 채택할 것과 채택하지 않을 것

| Gutenberg concept | Axismundi position |
| --- | --- |
| route-owned `sidebar`, `content`, `preview` areas | 채택 |
| Styles panel의 page shell, root-menu IA | UI/interaction reference로 채택 |
| `section`과 `preview` query의 분리 | 채택 |
| 실제 Frontend document를 iframe preview로 사용하는 control/preview 분리 | 채택 |
| `GlobalStylesUI` provider, block editor store, revisions, persistence | 미채택 |
| `StyleBookPreview` iframe implementation | 미채택 |
| block examples와 `<!-- wp:* -->` serialization을 Social layout DSL로 사용 | 미채택 |
| `--wp--preset-*`을 Social component API로 사용 | 미채택 |

## 외부 CSS와 Site Editor Style Book의 경계

Axismundi theme palette entries는 다음처럼 Material system custom property를 참조할 수 있다.

```json
{
  "slug": "primary",
  "color": "var(--md-sys-color-primary)"
}
```

Site Editor Style Book은 Global Styles preview다. `theme.json` 밖의 foundation stylesheet가
항상 그 preview iframe에 들어온다는 contract는 없다. 따라서 external `--md-ref-*` 또는
`--md-sys-*` CSS가 그 preview에서 resolve되지 않는 현상은 Gutenberg를 수정해 해결할
문제가 아니다.

Axismundi의 해법은 별도다.

```text
Theme foundation CSS
  -> /social/ runtime receives it directly
  -> Axismundi Admin app-owned Stylebook canvas resolves the same effective tokens
  -> Admin can embed that development canvas
```

## Current Frontend-first direction

Axismundi는 React application을 "classic frontend"로 먼저 구현한다.

```text
Theme
  -> foundation tokens and font-face resources

Social frontend
  -> React markup and routes
  -> app-owned global CSS
  -> scaffold, breakpoints, production components

Development Stylebook
  -> production-component fixture harness

Admin
  -> later control plane and registry consumer
  -> can embed the Stylebook development canvas
```

이 단계에서 Social global CSS는 실제 typography policy를 직접 적용할 수 있다.

```css
body { font-family: "Roboto Flex", system-ui, sans-serif; }
code, pre { font-family: "Roboto Mono", monospace; }
blockquote { font-family: "Roboto Serif", serif; }
```

font files와 `@font-face` output은 theme/Core resource layer의 책임이며, Social CSS는
semantic usage policy만 소유한다. 실제 product에서 검증된 policy만 나중에
`style.json`/Admin surface로 승격한다.

## Stylebook contract and distribution boundary

Stylebook은 데모 구현을 위한 별도 component library가 아니다. Frontend production component
source를 import해 fixture data/state로 검증한다. 그러나 이것이 Stylebook을 `/social/` product
route로 만든다는 뜻은 아니다.

WordPress의 `/wp-admin/admin.php`는 공용 admin front controller/URL transport다. Stylebook
route의 owner는 `?page=axismundi&p=/stylebook`으로 선택되는 Axismundi Admin app이며,
`admin.php` 자체가 아니다.

```text
production component implementation
  -> Social product: live data/state
  -> Stylebook: fixture data/state
```

Dialog처럼 behavior가 중요한 component는 production implementation을 그대로 사용하고,
Stylebook에는 draft fixture content를 공급한다. Stylebook은 component visual and interaction
verification surface이며, Admin preview는 그 surface의 consumer가 될 수 있다.

개발 중에는 Stylebook을 임시 route로 노출할 수 있다. component API와 fixture coverage가
안정된 뒤에는 그 route를 제거하거나, Stylebook host/bundle을 development-only build로
분리해 deployable plugin package에서 제외한다. 계속 배포되는 것은 production component
source이며, Stylebook route 자체가 아니다.

## Theme scheme ownership

theme/scheme runtime state는 Frontend가 소유한다. Admin iframe은 shared Frontend scheme
state의 effective result를 관찰한다.

```text
Frontend theme switcher
  -> persists the selected scheme through the Frontend-owned mechanism
  -> /social/ preview loads the same scheme and effective --md-sys-* values
```

나중에 Admin이 preview scheme을 요청해야 할 필요가 생기면, Admin이 CSS를 직접 소유하지
않고 `postMessage` bridge로 Frontend에 command를 보낸다.

## Component and scaffold conventions under consideration

다음은 schema가 아닌 Frontend implementation convention 후보이다.

```text
md-*  -> Material 3 spec-derived component
ax-*  -> Axismundi-owned/domain component

class -> component identity, part, structure, layout role
data-* -> non-default finite variant or application state
ARIA/native pseudo-class -> semantic/native interactive state
```

기본 visual variant는 markup에서 attribute를 생략한다.

```html
<button class="md-button">Save</button>
<button class="md-button" data-variant="outlined">Cancel</button>
```

component-specific selectors and variants belong in the Frontend `components` cascade layer.

## Admin experiment status

The current `/design/styles` implementation is an exploratory Admin shell, not a settled route
contract. It proved the following only:

- A Design sidebar, a central Styles control pane, and a Frontend iframe can coexist.
- `preview=stylebook` can select a Frontend fixture view.
- Admin can retain its own CSS while the iframe uses the real Frontend runtime.

It does not settle whether Styles remains a distinct `/design/styles` route or becomes a nested
Design workspace section. Before further Admin work, preserve this research and let the Frontend
scaffold, breakpoints, and production component model establish the product contract.

## Follow-up order

1. Build the Social scaffold and its breakpoint behavior.
2. Establish app-owned root/element typography policy using theme-delivered font faces.
3. Build Material production components and expose them through Frontend Stylebook fixtures.
4. Compose Social surfaces using the production components.
5. Extract stable registry/style/layout policy into Admin and manifests only where real product
   requirements justify it.

## References

- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/site-editor-routes/styles.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar-global-styles/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/global-styles-ui/src/global-styles-ui.tsx`
- `C:/Users/thaum/dev/gutenberg/packages/global-styles-ui/src/screen-root.tsx`
- `C:/Users/thaum/dev/gutenberg/packages/global-styles-ui/src/root-menu.tsx`
- `C:/Users/thaum/dev/gutenberg/packages/editor/src/components/style-book/index.jsx`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/docs/RESEARCH-ADMIN-ROUTE-AREAS.md`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/docs/RESEARCH-FRONTEND-WP-GLOBAL-STYLES.md`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/docs/DECISION-FRONTEND-THEME-ASSET-CONTRACT.md`
