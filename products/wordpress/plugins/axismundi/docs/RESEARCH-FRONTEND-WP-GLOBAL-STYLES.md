# 조사: Axismundi Frontend에 유입되는 WordPress preset CSS

## 상태

조사 완료. 2026-09-30.

이 문서는 `http://localhost:8884/social/`에서 `--wp--preset-*` custom
properties와 WordPress root style이 어떤 경로로 출력되는지 기록한다. 아직 public
route에서 이를 제거하지 않는다.

## 결론

`/social/`의 `--wp--preset-*`는 Axismundi theme foundation stylesheet가 아니라
WordPress/Gutenberg가 `wp_head()` lifecycle에서 자동으로 출력하는 inline stylesheet에서
들어온다.

주 source는 다음 element다.

```html
<style id="global-styles-inline-css">...</style>
```

이 stylesheet는 merged global styles를 출력하며 다음을 포함한다.

- `theme.json` settings에서 만든 `--wp--preset--color-*`, spacing, typography 등의 preset
  custom properties
- Core default palette, gradient, aspect-ratio, font-size 같은 default preset
- Axismundi `theme.json`의 root `styles`에서 만든 `body`, heading, link, button 등의
  WordPress presentation rule

현재 local environment는 Gutenberg plugin을 활성화하고 있으며, 실제
`wp_enqueue_scripts` hook의 priority `10` callback은
`gutenberg_enqueue_global_styles`다. Core function
`wp_enqueue_global_styles()`는 `wp-includes/script-loader.php`에 존재하지만, 이 환경의
effective callback은 Gutenberg implementation이다.

별도로 다음 element도 preset을 정의한다.

```html
<style id="wp-block-library-inline-css">...</style>
```

이것은 Core block-library inline CSS이고, 적어도
`--wp--preset--font-size--normal`과 `--wp--preset--font-size--huge`를 정의한다.

## Browser evidence

2026-09-30 localhost `/social/` DOM에서 확인한 결과:

```text
#global-styles-inline-css
  - 31,446 bytes
  - --wp--preset--color--primary: var(--md-sys-color-primary)
  - body { background-color: var(--wp--preset--color--surface); ... }
  - @font-face 없음

#wp-block-library-inline-css
  - 7,990 bytes
  - --wp--preset--font-size--normal: 16px
  - --wp--preset--font-size--huge: 42px
```

`axismundi-navigation-icons`와 `axismundi-theme-controls` stylesheet는 일부
`--wp--preset-*`를 참조하지만 정의하지는 않는다. 그러므로 reference를 제거하는 일과
Core/Gutenberg preset definition을 제거하는 일은 분리해서 판단해야 한다.

## Theme font-face output은 별도 경로

다음 inline stylesheet는 `global-styles-inline-css`와 별개다.

```html
<style id="wp-fonts-local">@font-face { ... }</style>
```

2026-09-30 localhost `/social/`에서는 Roboto Flex 두 face, Roboto Serif, Roboto Mono 두
face, Material Symbols Outlined를 포함한 여섯 `@font-face` rule을 확인했다.

현재 Gutenberg 23.7.1 환경에서 출력 경로는 다음과 같다.

```text
wp_head priority 50
  -> gutenberg_print_font_faces()
  -> gutenberg_get_font_face_styles()
  -> gutenberg_get_global_settings()
  -> settings.typography.fontFamilies[*].fontFace[]
  -> WP_Font_Face::generate_and_print()
  -> #wp-fonts-local
```

`gutenberg_get_font_face_styles()`는 `theme.json`의 font-family declaration을 읽고,
font face source의 `file:./` prefix를 `get_theme_file_uri()`로 theme URI로 바꾼다. 따라서
theme-relative bundled font assets는 WordPress가 계속 resolve하며, Plugin은 font path나
font resolver를 별도로 소유할 필요가 없다.

`#global-styles-inline-css`에는 `@font-face`가 없다. 그러므로 `/social/`에서 global
styles와 block-library CSS를 제거하더라도 `gutenberg_print_font_faces()`를 유지하면
theme-declared font faces는 그대로 남길 수 있다.

## 의미

`bbd2a13`의 foundation-only asset branch는 theme-owned CSS만 분리했다. 그러나 public
document가 여전히 `wp_head()`를 사용하므로 WordPress global style pipeline은 별도로
동작한다. 따라서 현재 `/social/`은 다음처럼 완전히 clean하지 않다.

```text
/social/
  theme foundation token CSS
  + plugin frontend CSS
  + WordPress/Gutenberg global-styles inline CSS
  + block-library inline CSS
```

Axismundi Frontend가 `--md-ref-*`와 `--md-sys-*`만 design-token authority로 가져가려면
public route에서 WordPress global styles와 block-library styles를 conditional하게
제외해야 한다.

## 후속 checkpoint

다음 구현은 theme asset contract와 분리한다.

1. `/social/`일 때 effective global styles callback을 제거한다.
   - Gutenberg 활성 환경: `gutenberg_enqueue_global_styles`
   - Core-only fallback: `wp_enqueue_global_styles`
2. `/social/`에서 block-library stylesheet가 불필요한지 확인한 뒤,
   `wp_common_block_scripts_and_styles`의 conditional exclusion 여부를 결정한다.
3. public document DOM에서 `#global-styles-inline-css`와
   `#wp-block-library-inline-css`가 사라졌는지 검증한다.
4. `#wp-fonts-local`과 theme foundation token CSS, plugin frontend bundle이 정상적으로
   남는지 검증한다.
5. unrelated plugin stylesheet와 그 안의 `--wp--preset-*` reference는 별도 asset-policy
   checkpoint에서 다룬다.

Font resolver나 theme font-face delivery는 이 checkpoint의 범위가 아니다.

## 레퍼런스

- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/includes/route.php`
- `C:/Users/thaum/dev/axismundi/products/wordpress/themes/axismundi/theme.json`
- `C:/Users/thaum/dev/axismundi/products/wordpress/themes/axismundi/functions.php`
- Local WordPress 7.1.2 with Gutenberg 23.7.1: `wp_enqueue_scripts` callback inspection
