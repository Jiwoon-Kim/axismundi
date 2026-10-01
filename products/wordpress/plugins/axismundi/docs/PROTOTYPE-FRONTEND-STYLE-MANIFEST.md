# 프로토타입: Axismundi Frontend `style.json`

## 상태

탐색 중. 2026-09-30.

이 문서는 Frontend 앱에 둔 [style.json](../src/apps/frontend/style.json) 초안의 현재
의도와 검토 경계를 보존한다. 이는 JSON Schema, runtime loader, generator 또는 Admin
editor의 명세가 아니다.

## 목적

`/social/`은 WordPress block-theme surface와 별개의 React/Material 3 application이다.
`style.json`은 그 application이 공유하는 foundation, global policy, native HTML baseline,
template composition vocabulary를 구조화해서 보관하는 exploratory manifest다.

```text
Axismundi theme
    -> --md-ref-* foundation tokens
    -> theme.json WordPress preset/global-style projection

style.json
    -> Frontend semantic policy
    -> --md-sys-* projection target
    -> Social root/element/template vocabulary
```

`style.json`은 `theme.json` 복제본, WordPress Global Styles engine, asset registry,
component runtime prop contract가 아니다.

## 위치와 현재 소비 상태

```text
src/apps/frontend/style.json
```

파일은 Frontend application이 소유한다. 현재 build와 runtime은 이 파일을 읽지 않는다.
이는 의도적이다. 구조를 schema와 loader로 고정하기 전에 manifest 자체를 검토하기 위한
checkpoint다.

`$schema`도 아직 넣지 않는다. canonical schema URL은 GitHub Pages 경로를 확보했지만,
`schemas/style/1.json`은 아직 작성하거나 배포하지 않았다.

## 현재 구조

```text
settings
foundations
  layout
  designTokens.md
styles
  color
  elevation
  icons
  motion
  shape
  spacing
  typography
  elements
templates
templateParts
```

### `settings`

현재 `colorScheme`은 theme color CSS가 이미 지원하는 `light`, `dark`, `auto`와
`data-theme` attribute contract를 기록한다. 이것은 new theme-switcher runtime을
등록하지 않는다.

### `foundations.layout`

`compact`과 `medium`의 경계는 theme의 `settings.viewport.mobile`과 `tablet`을
보존한다. `expanded`, `large`, `extraLarge`는 Social adaptive layout을 검토하기 위한
Material-style vocabulary다. 아직 CSS media query나 layout behavior를 생성하지 않는다.

`content`와 `wide`의 inline size는 현재 theme `settings.layout`의 검증된 값을
참조한 초기 후보다.

### `foundations.designTokens.md`

Theme CSS가 token value의 source다.

```text
assets/styles/tokens/tokens.ref.css
assets/styles/tokens/tokens.sys.color.light.css
assets/styles/tokens/tokens.sys.color.dark.css
assets/styles/tokens/tokens.sys.shape.css
assets/styles/tokens/tokens.sys.motion.css
assets/styles/tokens/tokens.sys.state.css
assets/styles/tokens/tokens.sys.elevation.css
```

`ref`는 theme source와 `--md-ref-` namespace를 기록한다. raw palette literal을 JSON에
복제하지 않는다.

`sys.color.light`와 `sys.color.dark`는 예외다. 이 두 mapping은
`DECISION-FRONTEND-STYLE-MANIFEST.md`에서 정한 Frontend semantic authority의 첫
실물이다. 각 Material color role은 `{ref.palette.*.*}` alias로 theme reference tone을
선언한다. 이후 generator 또는 runtime projection은 이 mapping으로
`--md-sys-color-*`를 emit한다.

shape, elevation, motion, state는 현재 theme-provided namespace만 기록한다. 개별 token
catalogue나 Frontend override policy는 아직 정하지 않았다.

### `styles`

`styles`는 resource definition이 아니라 Social application policy다.

- `color`는 root background, text, link가 사용할 M3 system role을 고른다.
- `typography.root`는 theme/Font Library resource의 file path가 아니라 family와 M3 type
  role identifier를 선택한다.
- `icons.default`는 Material Symbols rendering policy의 현재 후보일 뿐 Icon Registry나
  SVG transport를 대체하지 않는다.
- `elements`는 class 없는 semantic/native HTML baseline이다. `code`/`pre`의 mono,
  `blockquote`의 serif 같은 현재 theme precedent를 Social policy로 옮겨 검토한다.
- `elevation`, `motion`, `shape`, `spacing`은 top-level information architecture를
  시험하기 위해 비어 있다. 빈 object는 runtime default나 editable value를 뜻하지 않는다.

Component style policy는 이 파일에 넣지 않는다. `CAPSTONE.md`에 정의한 대로
colocated `component.json`은 registry, catalogue, fixture, build-check metadata이며
component implementation 또는 runtime prop contract를 중복하지 않는다.

### `templates`와 `templateParts`

현재 entries는 Social composition vocabulary의 이름만 보관한다.

```text
templates: feed, actor, actorGroup, object, objectArticle, objectTombstone,
           objectDocument, notification, collection

templateParts: scaffold.bar, scaffold.pane, scaffold.rail, dialogSurface
```

이것은 WordPress block-theme template file을 재사용하거나 Social template renderer를
구현한다는 뜻이 아니다. 이후 실제 composition contract가 독립 manifest여야 한다고
판단되면 이 namespace는 이동하거나 분리할 수 있다.

## 검증

초안 생성 시 다음을 확인했다.

- `style.json`은 유효한 JSON이다.
- light/dark color mapping 64개가 현재 theme의
  `tokens.sys.color.light.css` 및 `tokens.sys.color.dark.css` mapping과 일치한다.
- 현재 runtime/build에서 `style.json`을 import하지 않는다.

## 의도적으로 미정인 사항

- alias grammar와 JSON path -> CSS custom property 변환 규칙
- `style.json`이 sys color CSS를 생성할지, runtime에서 projection할지
- fluid typography의 Core `clamp()` 계산과 M3 type role의 관계
- Font, Icon, Emoji resource identifier resolution
- empty `styles` namespaces의 실제 field shape
- template/template-part composition schema와 persistence
- user override, validation, Admin editor, schema URL activation

## 관련 결정

- [Frontend style manifest와 Material 색상 정책](DECISION-FRONTEND-STYLE-MANIFEST.md)
- [Frontend theme asset contract](DECISION-FRONTEND-THEME-ASSET-CONTRACT.md)
- [Capstone component provenance와 metadata](CAPSTONE.md)
