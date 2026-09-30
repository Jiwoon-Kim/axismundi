# 결정 기록: Social Frontend Product First

## 상태

채택됨. 2026-10-01.

이 문서는 Axismundi Social Frontend, Stylebook, Admin의 구현 순서와 ownership을 정한다.
`style.json` schema, registry persistence, configurable template editor는 이 결정의 범위가
아니다.

## 결정

Social Frontend product를 Admin control plane보다 먼저 구현한다.

```text
Theme
  -> shared foundation tokens and font-face resources

Social Frontend
  -> product/runtime truth
  -> React markup, scaffold, CSS, production components
  -> Stylebook fixture surface

Admin
  -> later control plane
  -> registry and policy consumer
  -> embeds actual Frontend preview
```

Admin의 `sidebar | content | preview` shell은 유지한다. 단, Frontend product가 실제로
확정하기 전에는 Admin에서 Global Styles engine, template authoring, component playground,
registry write flow를 구현하지 않는다.

## React classic frontend

현재 Social은 WordPress block theme와 별개인 React application이다. 이 단계에서는
declarative editor를 먼저 만들지 않고, 코드가 canonical presentation source인
"React classic frontend"로 구현한다.

```text
Classic WordPress theme                 Social Frontend
-------------------------------------   ----------------------------------------
PHP templates                           React routes and components
theme style.css                         Frontend global CSS
functions.php asset loading             theme foundation asset contract
theme markup composition                React scaffold composition
Customizer/theme.json (later)           style.json/Admin policy layer (later)
```

이 비유의 목적은 WordPress PHP template를 재사용하는 것이 아니다. 실제 product를 먼저
코드로 만들고, 반복되어 안정된 policy만 나중에 structured manifest와 Admin UI로
승격한다는 구현 순서를 명확히 하는 것이다.

## Theme and Frontend boundary

Theme은 resource/foundation provider다.

```text
Theme owns
  - --md-ref-* and --md-sys-* foundation token values
  - light/dark foundation stylesheet cascade
  - theme-declared @font-face resources
  - resource path, version, dependency resolution

Social Frontend owns
  - application markup and routes
  - root and semantic HTML element styling
  - typography/resource usage policy
  - app scaffold and component CSS
```

Social은 `--wp--preset-*`이나 block theme selector를 component API로 소비하지 않는다.
theme-delivered Material tokens와 font resources를 Frontend CSS가 직접 사용한다.

### Typography policy now

`@font-face` resource discovery/output은 theme/Core가 담당한다. Social은 global CSS에서
실제 semantic role을 연결한다.

```css
body {
  font-family: "Roboto Flex", system-ui, sans-serif;
}

code,
pre {
  font-family: "Roboto Mono", monospace;
}

blockquote {
  font-family: "Roboto Serif", serif;
}
```

이 policy는 처음에는 Frontend stylesheet에 직접 둔다. 실제 product 요구가 확인된 뒤에만
`style.json`과 Admin Typography surface로 승격한다. font file path나 face source는
Frontend policy에 저장하지 않는다.

## Stylebook is a production harness

Social Stylebook은 문서 사이트나 별도 demo component library가 아니다. Frontend runtime
안에서 production component에 fixture data와 state를 공급하는 verification surface다.

```text
one production component
  -> Social product: live domain data and application state
  -> Stylebook: fixture data and controlled state
```

따라서 다음 분리는 금지한다.

```text
stylebook/ButtonDemo
Social/Button
```

Stylebook에는 동일한 `Button`, `Dialog`, `NavigationRail` 등의 implementation을 import한다.
Dialog의 body copy처럼 fixture content는 draft일 수 있지만 focus trap, Escape, backdrop,
responsive surface, restore behavior는 production Dialog가 제공해야 한다.

Admin preview는 Social Stylebook의 consumer다.

```text
Admin control pane
  -> iframe
      -> /social/ Frontend document
          -> Stylebook fixture or product route
```

Admin document에 Frontend/M3 cascade를 import해 visual copy를 만들지 않는다.

## Build order

Frontend의 component API는 layout context와 responsive behavior에서 결정된다. 따라서
component catalogue보다 scaffold와 breakpoint contract를 먼저 구현한다.

1. Frontend global stylesheet의 root/element typography policy
2. App scaffold regions and breakpoint behavior
3. Material production components
4. Axismundi domain components
5. Social product composition
6. Stylebook fixtures for those same components and scenarios
7. Admin registry/policy surfaces that consume proven product contracts

### Scaffold and responsive vocabulary

현재 exploratory `style.json`의 viewport vocabulary는 Frontend CSS/React behavior로 먼저
검증한다.

```text
compact      <= 599px
medium       600px - 839px
expanded     840px - 1199px
large        1200px - 1599px
extraLarge   >= 1600px
```

초기 scaffold는 semantic regions를 가진다.

```text
AppScaffold
  - navigation rail or compact navigation
  - main region
  - optional supporting pane
```

각 region의 collapse, persistence, visibility는 실제 Frontend stylesheet와 React layout에서
결정한다. 아직 layout JSON, drag/drop editor, template persistence를 만들지 않는다.

## Component conventions

### Ownership prefixes

```text
md-*  Material 3 specification-derived component
ax-*  Axismundi-owned or domain/product component
```

prefix는 token source가 아니라 component ownership/spec origin을 뜻한다. Material token을
사용하는 `ax-object-card`는 여전히 `ax-*` component다.

### Markup and CSS state

```text
class     component identity, part, structural/layout role
data-*    finite non-default variant or application state
ARIA      semantic interactive state where it exists
pseudo    native browser interaction state
```

예를 들어 filled button은 default markup으로 표현한다.

```html
<button class="md-button">Save</button>
<button class="md-button" data-variant="outlined">Cancel</button>
```

`aria-pressed`, `aria-selected`, `aria-expanded`, `:disabled`, `:focus-visible`, `:hover`처럼
semantic/native state가 있는 경우에는 `data-*` duplicate를 만들지 않는다.

component identity와 visual variant는 semantic HTML element와 독립적이다. action은
`<button>`, navigation은 `<a href>`를 사용하되 같은 component class를 적용할 수 있다.
`as` polymorphism은 accessibility contract를 지킬 수 있는 제한된 element set에서만
사용한다.

### Cascade layers

Frontend component-specific styles, parts, variants, and component states는 모두
`components` layer에 둔다.

```css
@layer components {
  .md-button { /* filled default */ }
  .md-button[data-variant="outlined"] { /* variant */ }
  .md-button[aria-pressed="true"] { /* semantic state */ }
}
```

Theme foundation tokens는 현재 unlayered CSS로 제공된다. Frontend는 global base, layout,
components layer를 자체 stylesheet에서 관리한다. token layer 재배치는 실제 cascade
requirement가 확인될 때만 한다.

## Composition and template scope

React component tree는 block composition hierarchy와 conceptually 대응할 수 있다.

```text
Feed
  -> FeedItem
      -> ActorHeader
      -> ObjectContent
      -> Media
      -> Actions
```

그러나 Frontend layout language로 Gutenberg block serialization을 사용하지 않는다.

```text
allowed:   structural/domain correspondence
not used:  <!-- wp:* --> serialization, block parser, InnerBlocks runtime
```

operator-defined, arbitrary template authoring을 지금 구현하지 않는다. 이것은 nested
composition, persistence, revisions, migrations, permissions, preview, undo/redo를 요구하며
Gutenberg를 재발명하는 방향이 된다.

Misskey는 modern social workspace와 widget/layout composition의 product reference가 될 수
있다. 그러나 그 사용자-customizable layout philosophy는 Admin의 global site policy와
동일하지 않다.

```text
Site/operator policy
  -> available components, allowed regions, defaults

User workspace preference
  -> permitted widget visibility, order, placement

Frontend runtime
  -> merges defaults and user preferences
```

이 user composition model도 scaffold/product requirements가 확인된 뒤에 도입한다. 현재는
code-defined default composition을 우선한다.

## Theme scheme ownership

theme/scheme selection은 Frontend runtime state다. Frontend가 선택을 Frontend-owned
persistence mechanism에 저장하면, Admin iframe은 동일 origin의 `/social/` document로서
effective scheme과 `--md-sys-*` 값을 렌더한다.

Admin이 나중에 preview scheme을 요청해야 하는 경우에도 theme CSS나 runtime state를 직접
소유하지 않는다.

```text
Admin
  -> postMessage command
  -> Frontend iframe
  -> Frontend applies its own scheme mechanism
```

이 bridge는 현재 구현하지 않는다.

## Admin status

Admin의 route-area shell, Design navigation, Assets inventory research는 유효하다. 다만
Admin은 product architecture의 source가 아니라 consumer다.

```text
Admin later
  - inspect production component registry
  - select/observe Stylebook fixtures
  - manage stable site defaults and allowed constraints
  - preview actual Frontend runtime in an iframe
```

현재 `/design/styles` split-pane experiment는 Admin shell이 iframe preview를 수용할 수
있음을 보여준 prototype다. 이것은 Styles route hierarchy, `style.json` registry, persistence,
or Site Editor parity를 확정하지 않는다.

## Deferred work

- `style.json` schema and public schema URL
- semantic token registry and `--ax-*` projection
- Global Styles-like merge/compiler
- Admin Styles write flow
- component JSON schema and registry runtime
- user widget layout persistence and editing UI
- Admin-to-iframe preview bridge

## Related research

- `RESEARCH-GUTENBERG-STYLES-WORKSPACE-REFERENCE.md`
- `RESEARCH-FRONTEND-WP-GLOBAL-STYLES.md`
- `RESEARCH-FONT-LIBRARY-WP-ADMIN.md`
- `DECISION-FRONTEND-THEME-ASSET-CONTRACT.md`
- `DECISION-FRONTEND-CORE-PRESENTATION-ISOLATION.md`
