# 조사 기록: WordPress Font Library wp-admin과 Axismundi Fonts

## 상태

조사 완료. 2026-09-30.

이 기록은 Font Library 전체를 Axismundi로 import하거나 복제하기 위한 구현 명세가
아니다. `Design > Assets > Fonts`가 WordPress native font system을 어떤 범위까지
참조하고 소비할 수 있는지, 그리고 Axismundi Frontend policy와 분리해야 하는 지점을
확인한 research spike다.

## 결론

`themes.php?page=font-library-wp-admin&p=/font-list`는 Fonts 관리 surface의 가장 좋은
UX와 domain reference다. 그러나 이 page 전체는 Axismundi에 직접 포트할 수 있는
독립 component가 아니다.

현재 Font Library는 다음을 하나의 편집 transaction으로 결합한다.

```text
installed font posts and font files
    +
active theme global styles: settings.typography.fontFamilies
    +
upload/install/uninstall lifecycle
```

Axismundi Frontend는 block theme가 아닌 독립 React application이다. 따라서 Axismundi
Fonts screen이 Font Library의 UI를 참고할 수는 있어도, `wp_global_styles`를 곧바로
Axismundi Frontend font policy로 취급하거나 수정해서는 안 된다.

채택할 방향은 다음이다.

```text
WordPress Font Library APIs and records
    -> Axismundi Admin Fonts hub: discovery and management projection
    -> separate Axismundi Frontend delivery policy
```

## 실행 화면 확인

다음 localhost page를 확인했다.

```text
http://localhost:8884/wp-admin/themes.php?page=font-library-wp-admin&p=/font-list
```

현재 실행 환경에서는 WordPress admin chrome과 Font Library의 WPDS root가 mount되지만
content area가 비어 있었다. browser console error는 없었다. 따라서 이 실행 화면만을
정상 UI reference로 삼지 않고, 같은 checkout의 Gutenberg route, reusable component,
Core page integration, E2E specification을 함께 대조했다.

## 원본 레퍼런스

- `C:/Users/thaum/dev/gutenberg/routes/font-list/stage.tsx`
- `C:/Users/thaum/dev/gutenberg/routes/font-list/route.ts`
- `C:/Users/thaum/dev/gutenberg/packages/global-styles-ui/src/font-library/font-library.tsx`
- `C:/Users/thaum/dev/gutenberg/packages/global-styles-ui/src/font-library/context.tsx`
- `C:/Users/thaum/dev/gutenberg/packages/global-styles-ui/src/font-library/installed-fonts.tsx`
- `C:/Users/thaum/dev/gutenberg/packages/global-styles-ui/src/font-library/font-collection.tsx`
- `C:/Users/thaum/dev/gutenberg/packages/global-styles-ui/src/font-library/upload-fonts.tsx`
- `C:/Users/thaum/dev/gutenberg/test/e2e/specs/admin/font-library.spec.js`
- `C:/Users/thaum/dev/wordpress-develop/src/wp-includes/build/pages/font-library/page-wp-admin.php`
- `C:/Users/thaum/dev/gutenberg/packages/core-data/src/entities.js`
- `C:/Users/thaum/dev/gutenberg/packages/core-data/src/entity-types/font-family.ts`

## Core page anatomy

### Standalone wp-admin application

`font-library-wp-admin` page는 일반 content component가 아니라 route module을
`@wordpress/boot`의 `initSinglePage()`로 mount하는 standalone application이다. Core
page integration은 REST preload, script-module dependency, full-page CSS reset,
legacy wp-admin content/footer hiding, `#font-library-wp-admin-app` mount point를 함께
소유한다.

```text
font-library-wp-admin PHP page
    -> font-library-wp-admin_init action
    -> registered route modules
    -> @wordpress/boot initSinglePage()
    -> #font-library-wp-admin-app
```

Axismundi는 이미 own `route -> areas -> layout` shell을 가진다. 이 page bootstrap,
private boot dependency, wp-admin host reset은 Axismundi로 가져오지 않는다.

### Route stage

`routes/font-list/stage.tsx`는 page title, tab list, font collection discovery와
`FontLibrary` composition을 담당한다. stage는 다음 private API에 의존한다.

- `@wordpress/components` private `Tabs`
- `@wordpress/editor` private `useGlobalStyles`

그 결과 `FontLibrary`에 현재 global styles `base`, user override `user`, 그리고
`setUser` write callback을 전달한다. 이 stage 자체도 Axismundi의 direct import target이
아니다.

## Reusable Font Library component의 실제 결합

`FontLibrary`는 보기에는 작은 tab content switch이지만 다음 provider 조합을 필수로
요구한다.

```tsx
<GlobalStylesProvider value={ user } baseValue={ base } onChange={ setUser }>
    <FontLibraryProvider>{ content }</FontLibraryProvider>
</GlobalStylesProvider>
```

`FontLibraryProvider`는 다음 authority를 동시에 읽고 쓴다.

- `postType/wp_font_family` records와 embedded `font_faces`
- current `root/globalStyles` entity
- `settings.typography.fontFamilies` setting
- `saveEntityRecord( 'root', 'globalStyles', ... )`
- `deleteEntityRecord( 'postType', 'wp_font_family', ... )`
- font family/face upload and install REST lifecycle

Install은 font files와 `wp_font_family` posts를 만든 뒤 custom font family를 global
styles에 activate한다. Uninstall은 post/files를 삭제하고 global styles activation도
제거한다. E2E test도 upload, activate, post editor font selection, public block-theme
frontend rendering, uninstall을 하나의 flow로 검증한다.

이는 WordPress block-theme Font Library의 올바른 lifecycle이지만 Axismundi Frontend의
font delivery policy와 동일하지 않다.

## Native data sources

### Font collections

`@wordpress/core-data`는 다음 public entity를 제공한다.

```text
root/fontCollection
GET /wp/v2/font-collections
key: slug
context: view
```

Font collection browse/search UI는 이 record를 native source of truth로 쓴다.

### Installed fonts

Installed font family와 face data는 `postType/wp_font_family` record와 embedded
`font_faces`에서 읽는다. Core type은 `font_family_settings` 안에 다음처럼
theme.json-compatible data를 둔다.

```text
name, slug, fontFamily, fontFace
fontStyle, fontWeight, fontStretch, src
fontDisplay, fontVariationSettings, unicodeRange
```

이 API와 record shape는 Axismundi Fonts hub가 read-only inventory를 시작할 현실적인
native source다.

## Axismundi에 가져올 것과 가져오지 않을 것

### 가져올 것

- installed/library/collection/upload라는 information architecture reference
- font family card, family detail, face/variant presentation, loading/empty/error state
- Core data entity와 REST capability discovery
- upload 전 font file metadata validation이라는 lifecycle pattern
- user-facing write action의 permission/error/confirmation model

### 직접 가져오지 않을 것

- `font-library-wp-admin` page bootstrap, module router, page CSS host reset
- `@wordpress/editor` private `useGlobalStyles`
- `@wordpress/components` private `Tabs`
- `GlobalStylesProvider`와 `FontLibraryProvider` 전체를 black-box dependency로 import
- Font Library가 수행하는 `wp_global_styles` activation을 Axismundi Frontend policy로
  자동 연결하는 동작
- `font-library-*` CSS namespace를 Axismundi source ownership으로 재사용하는 동작

## Axismundi Fonts의 결정 전 질문

Font page의 write UI를 port하기 전에 다음 authority를 별도 결정해야 한다.

```text
Axismundi에서 font install/enable/disable은 무엇을 변경하는가?

1. WordPress Font Library records/files만 관리한다.
2. active block theme global styles도 함께 변경한다.
3. Axismundi Frontend의 별도 font delivery policy만 변경한다.
4. 둘 이상의 target을 명시적으로 선택하게 한다.
```

현재 Axismundi architecture에서는 3이 필요한 것이 확실하다. 2는 block theme와
Axismundi Frontend가 의도적으로 같은 presentation policy를 공유할 때만 선택할 수
있다. 따라서 read/write lifecycle을 Font Library에서 그대로 포트하기 전에 이 결정을
먼저 기록해야 한다.

## 권장 후속 checkpoint

1. `Design > Assets > Fonts`에 native records의 **read-only inventory**를 만든다.
   `wp_font_family`와 `fontCollection`을 각각 표시하되 Axismundi route/layout/sidebar를
   유지한다.
2. Font Library의 family card와 variant detail을 visual/domain reference로만 local
   composition한다. private Gutenberg provider/router/CSS는 import하지 않는다.
3. Axismundi Frontend font delivery policy를 별도 decision record로 정의한다.
4. 그 결정 뒤에만 upload/install/enable/remove action의 target과 capability model을
   정한다.

Icons catalogue의 generic abstraction은 이 단계에서도 만들지 않는다. Font와 icon은
각 native data model과 lifecycle을 먼저 존중하고, 실제 consumer 둘이 생긴 뒤에만
공유 UI primitive를 추출한다.
