# 결정 기록: Axismundi Frontend와 Theme Foundation Asset Contract

## 상태

채택됨. 2026-09-30.

이 문서는 Axismundi `/social/` Frontend가 Axismundi theme의 foundation stylesheet를
어떻게 소비할지 결정한다. 아직 theme loader 분리나 `style.json` generator를 구현하지
않는다.

## 문제

Axismundi Frontend는 block theme가 아닌 독립 React application이지만, M3 foundation
token은 Axismundi theme가 소유한다. 현재 theme `functions.php`는 다음 responsibility를
이미 가진다.

- theme-relative path resolution: `axismundi_asset_uri()`
- asset cache busting: `axismundi_asset_version()`
- dependency order
- front, editor canvas, editor UI의 context-specific loading

Plugin이 이 path, `file_exists()`, `filemtime()`, dependency order를 다시 구현하면
theme asset loader가 둘이 된다.

반대로 Plugin이 현재 `axismundi_enqueue_assets()`를 호출하거나, `/social/`의
`wp_head()`가 기존 theme enqueue hook을 그대로 실행하게 두면 token 외에도 block and
theme component CSS까지 public React app에 들어온다. 이것도 Frontend/Admin visual
boundary에 맞지 않는다.

## 관찰한 현재 구조

Axismundi theme `axismundi_enqueue_assets()`는 하나의 ordered array에 다음을 함께
가진다.

```text
foundation
  tokens.ref
  tokens.sys.color.light/dark
  tokens.sys.shape/elevation/state/motion

theme and block presentation
  icons.css
  components.button.css
  components.select.css
  blocks.*.css
```

Plugin public route는 custom document를 출력하지만 `<head>`에서 `wp_head()`를 호출한다.
따라서 current theme의 `wp_enqueue_scripts` hook도 이 document에서 실행된다.

```text
/social/
  template_redirect
    -> plugin enqueue frontend bundle
    -> custom document
       -> wp_head()
          -> current theme full enqueue
```

2026-09-30 localhost `/social/`에서 이 동작을 실제로 확인했다. Foundation token 뒤에
`icons.css`, `components.button.css`, `components.select.css`, 그리고
`blocks.text.css`부터 `blocks.navigation-submenu.css`까지 모두 stylesheet로 출력된다.
이는 현재 public React app이 theme/block presentation cascade를 과도하게 받는
known gap이다.

## 결정

Theme은 asset location, existence, version, dependency order의 authority다. Plugin
Frontend는 asset path를 hard-code하거나 별도 PHP resolver를 만들지 않고, theme가
공개한 foundation asset contract를 consumer로 사용한다.

```text
Theme
  owns: asset descriptors and resolver/loader

Plugin Frontend
  owns: when the Social application needs foundation assets
  consumes: public theme foundation contract
```

`/social/`은 foundation만 받아야 하며 block/theme component cascade는 받지 않는다.

## Contract shape

Theme은 public function으로 foundation descriptor를 제공한다. 정확한 PHP signature는
후속 구현에서 확정하지만 shape는 다음 정도를 유지한다.

```php
axismundi_get_foundation_style_assets(): array
```

각 descriptor는 theme-relative path와 dependency를 가진다. URI와 version은 theme
function이 resolve한다.

```php
array(
    'tokens-ref' => array(
        'path' => 'assets/styles/tokens.ref.css',
        'deps' => array(),
    ),
    'tokens-shape' => array(
        'path' => 'assets/styles/tokens.sys.shape.css',
        'deps' => array( 'tokens-ref' ),
    ),
)
```

Theme의 normal block-theme front는 foundation과 block/component asset 모두를 enqueue한다.
Plugin public route에서는 theme의 foundation-only loader branch가 같은 descriptor를
enqueue한다.

```text
theme page
  foundation + theme/block assets

/social/
  foundation only + Axismundi frontend bundle
```

Plugin이 theme function을 직접 호출해 duplicate enqueue를 만드는 방식보다, theme loader가
public filter/contract를 통해 current document의 requested asset mode를 알아 foundation-only
branch를 선택하는 방식이 우선이다. 그러면 URI/version/handle ownership도 theme에 남고,
`wp_head()`의 normal lifecycle과 충돌하지 않는다.

## `style.json`과 color output

`tokens.ref.css`, shape, elevation, state, motion은 theme foundation asset이다.

Color semantic mapping은 `style.json`이 single authoring source가 된다. `style.json`의
build output인 `tokens.sys.color.light.css`와 `tokens.sys.color.dark.css`는 existing theme
asset paths를 유지할 수 있다. 이 경우 theme foundation contract는 generated CSS를
ordinary stylesheet처럼 소비한다.

```text
style.json
  -> generator
  -> theme assets/styles/tokens.sys.color.light.css
  -> theme foundation asset contract
  -> /social/ and theme/editor consumers
```

Plugin runtime은 JSON을 읽어 CSS custom property를 매 request에 emit하지 않는다. JSON은
authoring/build input이고 runtime은 generated stylesheet를 읽는다.

## 제외 범위

- Plugin 내부에 second asset resolver 또는 `functions.php` equivalent 추가
- `axismundi_enqueue_assets()` 전체를 `/social/`에서 호출
- `/social/`에서 block CSS/component CSS를 load
- other theme discovery and fallback contract
- `style.json` schema, generator implementation, user override storage
- Admin Styles editor

## 후속 구현 checkpoint

1. Theme `functions.php`에서 foundation descriptor와 block/theme descriptor를 분리한다.
2. Theme normal front loader는 두 layer를 enqueue한다.
3. `/social/` route일 때 public contract를 통해 foundation-only branch를 선택한다.
4. `/social/` document에서 block/component CSS가 나오지 않고 foundation + frontend bundle만
   나오는 것을 browser network/DOM으로 검증한다.
5. 이후 `style.json -> generated tokens.sys.color.*.css`를 구현한다.

## 레퍼런스

- `C:/Users/thaum/dev/axismundi/products/wordpress/themes/axismundi/functions.php`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/includes/assets.php`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/includes/route.php`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/docs/DECISION-FRONTEND-STYLE-MANIFEST.md`
