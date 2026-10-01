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
  -> production component source

Development Stylebook
  -> fixture and verification harness for those components

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
  - --md-ref-* and shared --md-sys-* foundation token values for color, shape,
    elevation, state, and motion
  - light/dark foundation stylesheet cascade
  - theme-declared @font-face resources
  - resource path, version, dependency resolution

Social Frontend owns
  - application markup and routes
  - its own M3 typescale token declarations and typography policy
  - root and semantic HTML element styling
  - typography/resource usage policy
  - app scaffold and component CSS
```

Social은 `--wp--preset-*`이나 block theme selector를 component API로 소비하지 않는다.
theme-delivered Material tokens와 font resources를 Frontend CSS가 직접 사용한다.

### Typography ownership

`@font-face` resource discovery/output과 `theme.json` font family/font size preset은
theme/Core가 담당한다. 그러나 theme foundation asset contract에는 typography typescale
stylesheet가 없다. `theme.json` preset은 Social runtime의 `--md-sys-typescale-*` source가
아니다.

따라서 Social은 `axismundi.typography` layer에서 자체 M3 typescale token과 semantic HTML
role mapping을 선언한다. font resource path나 `@font-face` source는 Frontend stylesheet에
복제하지 않는다. typescale role, metric, HTML mapping은 별도 Typography contract에서
결정한다.

## Public route access policy

Social은 로그인 여부로 전체 application을 redirect하지 않는다. route와 object visibility가
표시 surface를 결정한다.

```text
/social/
  authenticated viewer -> home feed surface
  guest                -> signed-out home surface and sign-in affordance

/social/objects/:id
  public object        -> direct rendering for guest or signed-in viewer
  non-public object    -> future data layer access decision
```

초기 bootstrap은 `viewer.authenticated` boolean만 제공한다. 개인 identity, capability, REST
nonce, private data는 실제 authenticated data flow가 필요해질 때 별도로 제공한다.

이 policy는 처음에는 Frontend stylesheet에 직접 둔다. 실제 product 요구가 확인된 뒤에만
`style.json`과 Admin Typography surface로 승격한다. font file path나 face source는
Frontend policy에 저장하지 않는다.

## Stylebook is a production-component harness

Stylebook은 문서 사이트나 별도 demo component library가 아니다. Frontend가 소유한
production component에 fixture data와 state를 공급하는 development verification surface다.

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

Admin preview는 Stylebook harness의 consumer가 될 수 있다. 다만 Stylebook은 Social product
route의 일부가 아니다. Social은 사용자-facing application route만 소유하고, Stylebook은
Axismundi Admin app-owned development surface로 별도 canvas에서 제공한다. WordPress의
`/wp-admin/admin.php`는 Admin app을 여는 공용 front controller/URL transport일 뿐 Stylebook의
owner가 아니다. 예를 들어 `?page=axismundi&p=/stylebook`에서 `p=/stylebook`은 Axismundi
Admin app route를 선택한다.

```text
Admin control pane
  -> iframe
      -> Axismundi Admin app-owned Stylebook canvas
          -> Frontend production components + fixture state
```

Admin document에 Frontend/M3 cascade를 import해 visual copy를 만들지 않는다.

### Development-only route and distribution boundary

컴포넌트 개발 중에는 Stylebook harness를 route로 노출할 수 있다. 이 route는 개발과 VQA를
위한 임시 진입점이며 Social product route나 public application feature가 아니다.

컴포넌트 API와 fixture coverage가 안정된 뒤에는 다음 중 하나로 정리한다.

1. development route를 제거한다.
2. route와 bundle을 development-only build로 분리하고, deployable plugin package에서는
   제외한다.

어느 경우에도 production component source와 fixture definition은 유지할 수 있다. 배포
artifact에서 제외하는 대상은 Stylebook host/route와 development harness이지, 검증된
component implementation이 아니다.

## Build order

Frontend의 component API는 layout context와 responsive behavior에서 결정된다. 따라서
component catalogue보다 scaffold와 breakpoint contract를 먼저 구현한다.

1. Color foundation VQA
2. App scaffold regions, breakpoint behavior, and layout VQA
3. Typography contract, typescale layer, and typography VQA
4. Material production components
5. Axismundi domain components
6. Social product composition
7. Stylebook fixtures for those same components and scenarios
8. Admin registry/policy surfaces that consume proven product contracts

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
AppLayout
  - navigation rail or compact navigation
  - main region
  - optional supporting pane
```

각 region의 collapse, persistence, visibility는 실제 Frontend stylesheet와 React layout에서
결정한다. 아직 layout JSON, drag/drop editor, template persistence를 만들지 않는다.

### Layout implementation boundary

`AppLayout` 같은 layout은 region을 배치할 뿐 Navigation Bar, Navigation Rail, supporting
pane의 visual/component implementation을 제공하지 않는다. 각 surface는 독립 component가
나중에 slot으로 주입한다.

layout CSS는 `src/apps/frontend/layouts/`에 colocate하고 `axismundi.layouts` cascade layer에만
둔다. layout rule은 theme-delivered `--md-sys-*` token과 structural CSS grid/logical property를
사용한다. raw color, spacing, radius, elevation, typography metric을 layout selector에 새로
쓰지 않는다. CSS custom property가 media-query condition을 표현할 수 없으므로, 이미 정한
breakpoint threshold만 layout media query에 literal로 남긴다.

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

Frontend typescale token declarations and semantic HTML typography mappings are owned by
`axismundi.typography`. Component-specific styles, parts, variants, and component states are
owned by their component layer.

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

### Current theme-controls bridge

The existing Theme Controls plugin is not a `theme.json` palette registry and does not
import Site Editor custom colors. It is a pre-generated Material scheme picker.

```text
schemes.css
  -> :root[data-ax-scheme] overrides --md-ref-palette-*
  -> existing theme foundation resolves the effective --md-sys-color-* values

pre-paint head script
  -> restores data-ax-scheme from the axismundi_scheme cookie before first paint

theme-controls JS + mount
  -> renders the available scheme buttons
  -> updates the cookie and data-ax-scheme
```

`/social/` does not call `wp_footer()`, because that would admit every active plugin's
footer callback. The public route has an explicit footer allowlist instead. Theme Controls
is presently admitted through that allowlist as a working Color VQA control; its mount and
script are therefore live, not dead assets.

The bridge can later be replaced by a Frontend-owned Material component that preserves the
cookie and `data-ax-scheme` contract. It must not be mistaken for either:

```text
theme.json palette metadata registry
Site Editor user/custom palette import
```

The remaining Social `<head>` and public-route assets have not yet been reduced to a final
allowlist. Emoji, sensitive-media, PWA, discovery metadata, and Core fallback output require
separate product-level decisions.

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
- `DECISION-FRONTEND-NAVIGATION-MODEL.md`
- `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md`
- `DECISION-FRONTEND-SPATIAL-FOUNDATION.md`
- `REFERENCE-M3-CANONICAL-LAYOUTS.md`
- `RESEARCH-FRONTEND-WP-GLOBAL-STYLES.md`
- `RESEARCH-FONT-LIBRARY-WP-ADMIN.md`
- `DECISION-FRONTEND-THEME-ASSET-CONTRACT.md`
- `DECISION-FRONTEND-CORE-PRESENTATION-ISOLATION.md`
