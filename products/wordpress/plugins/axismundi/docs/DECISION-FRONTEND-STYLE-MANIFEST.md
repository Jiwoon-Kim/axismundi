# 결정 기록: Axismundi Frontend `style.json`과 Material 색상 정책

## 상태

채택됨. 2026-09-30.

이 문서는 `style.json`의 역할과 ownership을 고정한다. 아직 schema, runtime loader,
Admin editor 또는 write flow를 구현하지 않는다.

## 전제

현재 active theme는 Axismundi이며, theme가 다음 presentation asset을 이미 제공한다고
가정한다.

```text
assets/styles/tokens.ref.css
assets/styles/tokens.sys.color.light.css
assets/styles/tokens.sys.color.dark.css
assets/styles/tokens.sys.shape.css
assets/styles/tokens.sys.motion.css
assets/styles/tokens.sys.state.css
assets/styles/tokens.sys.elevation.css
theme.json
```

이 단계에서 다른 theme의 asset discovery, fallback, capability negotiation은 다루지
않는다.

## 문제

WordPress `theme.json`은 block theme와 editor가 쓰는 palette, typography preset,
global styles contract다. Axismundi `/social/`은 block theme가 아닌 독립 React
application이므로 Frontend가 다음처럼 WordPress naming을 직접 소비하게 만들면
presentation authority가 섞인다.

```css
--wp--preset--color--surface
--wp--preset--color--primary
```

반대로 Axismundi Frontend는 Material semantic token을 직접 소비해야 한다.

```css
--md-sys-color-surface
--md-sys-color-primary
--md-sys-color-on-primary
```

## 관찰한 현재 theme 구조

`products/wordpress/themes/axismundi/assets/styles/tokens.ref.css`는 raw/reference
palette token을 제공한다. 예를 들면 다음 계열이다.

```css
--md-ref-palette-primary-40
--md-ref-palette-neutral-98
--md-ref-palette-neutral-variant-50
```

`tokens.sys.color.light.css`와 `tokens.sys.color.dark.css`는 현재 Material system role을
reference tone에 연결하는 concrete baseline이다.

```css
--md-sys-color-primary: var(--md-ref-palette-primary-40);
--md-sys-color-surface: var(--md-ref-palette-neutral-98);
```

현재 Axismundi theme `theme.json`의 palette도 raw color literal이 아니라 이
`--md-sys-color-*` role을 참조한다.

```json
{
  "slug": "surface",
  "color": "var(--md-sys-color-surface)"
}
```

즉 현재 관계는 이미 다음과 같다.

```text
theme reference tokens
    -> Material system tokens
        -> WordPress theme.json presets
```

이 CSS mapping은 `style.json` 도입 전의 현재 implementation이다. `style.json`이
도입된 뒤에 같은 semantic mapping을 CSS와 JSON에서 각각 수동 관리하지 않는다.

## 결정

Axismundi Frontend는 `style.json`을 별도 **semantic design-policy manifest**로
사용한다.

```text
theme reference tokens
    -> style.json: Frontend semantic palette mapping
        -> generated or runtime-projected Material system variables

theme reference tokens
    -> theme.json: WordPress block/editor preset projection
```

`style.json`은 `theme.json`의 복제본도, CSS stylesheet도, Font/Icon/Emoji asset
registry도 아니다. Frontend가 어떤 semantic role을 어떤 available resource와 token으로
해석하는지 선언한다.

색상 mapping의 editable authority는 `style.json` 하나다. 현재
`tokens.sys.color.light.css`와 `tokens.sys.color.dark.css`는 migration 동안의
implementation baseline으로 유지할 수 있지만, 장기적으로는 `style.json`에서
생성하거나 동일 manifest를 runtime에서 projection한 결과여야 한다.

## 색상 역할 분리

### Theme-owned reference layer

Axismundi theme은 palette tone과 other M3 foundation token을 공급한다.

```text
--md-ref-palette-*
```

이는 raw design asset/foundation이다. Frontend component가 이 layer를 직접 소비하는
것은 기본 경로가 아니다.

### App-owned semantic layer

`style.json`은 light/dark scheme별 Material semantic role mapping을 선언한다. build 또는
runtime은 이 mapping을 `--md-sys-color-*`로 projection한다.

초기 schema의 방향은 다음처럼 reference를 보존하는 형태다. 이것은 확정 schema가
아닌 intended shape다.

```json
{
  "version": 1,
  "color": {
    "light": {
      "primary": "{ref.palette.primary.40}",
      "onPrimary": "{ref.palette.primary.100}",
      "surface": "{ref.palette.neutral.98}",
      "onSurface": "{ref.palette.neutral.10}"
    },
    "dark": {
      "primary": "{ref.palette.primary.80}",
      "onPrimary": "{ref.palette.primary.20}",
      "surface": "{ref.palette.neutral.6}",
      "onSurface": "{ref.palette.neutral.90}"
    }
  }
}
```

실제 Frontend component는 reference alias나 `--wp--preset--*` 이름이 아니라
`--md-sys-color-*`만 읽는다.

### WordPress projection layer

`theme.json` palette는 block/editor compatibility와 WordPress UI를 위해 유지한다.
이는 Axismundi Frontend의 direct color API가 아니다. `style.json`에서 projection된 같은
Material semantic role을 WordPress preset이 참조한다.

```text
--md-sys-color-surface
    -> --wp--preset--color--surface
```

방향은 one-way다. Frontend가 `--wp--preset--*`를 source로 삼아 Material token을
재구성하지 않는다.

## Assets 및 font policy와의 관계

Assets는 실제 resource inventory이고 `style.json`은 resource usage policy다.

```text
Assets > Fonts
    -> installed/available font resources

Assets > Icons
    -> registered icon resources

Assets > Emojis
    -> Unicode/custom emoji resources

style.json
    -> Frontend typography, icon rendering, emoji rendering policy
```

이 구분 때문에 `Installed`나 `Registered`는 `Used by Axismundi Frontend`를 뜻하지
않는다. Font Library의 `wp_global_styles` activation도 Axismundi Frontend enablement와
동일하지 않다.

## 제외 범위

- `style.json` v1 전체 schema 확정
- theme CSS를 parse하여 JSON을 자동 생성하는 build step
- `theme.json`이나 WordPress global styles의 write
- Admin Styles editor 구현
- Frontend runtime loader와 CSS custom property emission
- active theme가 Axismundi가 아닐 때의 discovery/fallback
- typography, icon, emoji policy의 세부 schema

## 후속 작업

1. `style.json` v0 schema spike에서 color mapping만 정의한다.
2. Axismundi theme reference token과 mapping target의 validation 규칙을 정의한다.
3. 현재 `tokens.sys.color.*.css`와 `style.json`의 migration/generation ownership을
   결정해 semantic mapping의 source가 둘이 되지 않게 한다.
4. Frontend runtime이 `style.json`의 light/dark scheme을 `--md-sys-color-*`로 emit하는
   경계를 설계한다.
5. Design > Styles를 이 manifest의 read/write management surface로 설계한다.
6. Fonts inventory는 native WordPress resource discovery로 계속 별도 진행한다.

## 레퍼런스

- `C:/Users/thaum/dev/axismundi/products/wordpress/themes/axismundi/theme.json`
- `C:/Users/thaum/dev/axismundi/products/wordpress/themes/axismundi/assets/styles/tokens.ref.css`
- `C:/Users/thaum/dev/axismundi/products/wordpress/themes/axismundi/assets/styles/tokens.sys.color.light.css`
- `C:/Users/thaum/dev/axismundi/products/wordpress/themes/axismundi/assets/styles/tokens.sys.color.dark.css`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/docs/DECISION-DESIGN-ASSETS-IA.md`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/docs/RESEARCH-FONT-LIBRARY-WP-ADMIN.md`
