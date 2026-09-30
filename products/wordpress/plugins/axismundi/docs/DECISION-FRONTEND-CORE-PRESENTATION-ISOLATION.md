# 결정 기록: Axismundi Frontend의 Core Presentation Isolation

## 상태

구현됨. 2026-09-30.

이 문서는 standalone `/social/` document에서 WordPress/Gutenberg Core의 presentation
policy를 어떤 범위까지 제외할지 결정한다. 다른 Axismundi plugin의 enqueue architecture는
이 checkpoint의 대상이 아니다.

## 문제

`/social/`은 custom document에서도 `wp_head()`를 사용한다. 따라서 WordPress/Gutenberg는
theme foundation asset과 무관하게 block-theme presentation CSS를 자동 출력한다.

```text
global-styles-inline-css
  - theme.json preset custom properties
  - theme.json root styles
  - Core default presets

wp-block-library-inline-css
  - Core block helpers and default presets

core-block-supports-inline-css
  - block support presentation rules
```

이 규칙은 React application의 `--md-ref-*` / `--md-sys-*` token authority와 충돌한다.

## 결정

`/social/`에서만 Core/Gutenberg presentation policy를 제거한다.

```text
remove
  global styles
  block library
  block library theme styles
  Core block supports

keep
  theme foundation token CSS
  #wp-fonts-local
  wp-img-auto-sizes-contain-inline-css
  Core emoji runtime and styles
  front-end admin bar and Dashicons when enabled
  all non-Core plugin integrations
  Axismundi frontend bundle and its declared dependencies
```

Gutenberg compatibility layer가 active일 때 effective callback은
`gutenberg_enqueue_global_styles`다. Core-only runtime compatibility를 위해
`wp_enqueue_global_styles`도 함께 remove한다. `wp_common_block_scripts_and_styles`는
other plugin asset registration에도 관여할 수 있으므로 제거하지 않는다. 대신
`global-styles`, `wp-block-library`, `wp-block-library-theme`, `core-block-supports`
handle만 final guard로 dequeue한다.

`axismundi-navigation-icons`는 Core `navigation` block의 rendered presentation
extension이므로 `/social/`에서 style과 view script를 dequeue한다. 이는 다른 plugin의
enqueue architecture를 refactor하는 작업이 아니라 standalone app document에서 known block
presentation extension을 제외하는 route policy다.

Korean, Japanese, Traditional Chinese Font Provider도 현재는 block-theme/editor asset path로
분류한다. `/social/`은 `#wp-fonts-local`만 font resource로 허용하고 세 provider stylesheet는
dequeue한다. Regional fallback은 Frontend가 독립 asset policy를 갖는 후속 checkpoint에서
명시적으로 opt-in한다.

`gutenberg_print_font_faces()`는 `wp_head` priority 50의 별도 callback이므로 제거하지
않는다. 이 callback은 theme.json font face declaration을 `#wp-fonts-local`로 출력한다.
`#wp-fonts-local`은 resource declaration일 뿐 typography policy는 아니다. Global Styles를
제거한 뒤 `/social/`의 `body`는 WordPress `--wp--preset--font-family-*`를 더 이상
소비하지 않는다. Frontend가 root font family, size, line height를 자신의 stylesheet 또는
future `style.json` generated CSS에서 명시해야 한다.

## 경계

이 결정은 "WordPress를 전부 제거"하는 정책이 아니다.

```text
WordPress resource/runtime capability
  -> explicit allowlist candidate

WordPress block-theme presentation policy
  -> /social/에서 제외
```

Axismundi Emoji, Media Sensitive, Theme Controls, PWA 같은 plugin asset은 이번 변경에서
변경하지 않는다. 각각이 frontend integration인지 legacy global enqueue인지는 별도 plugin
audit에서 판단한다. Navigation Icons와 regional Font Provider만 known block-theme/editor
extension으로 명시적으로 제외한다.

Frontend-owned CSS/JS도 ordinary frontend, wp-admin, Site Editor, Block Editor에 enqueue되지
않아야 한다. 현재 frontend bundle은 `axismundi_capstone_enqueue_app( 'frontend' )`를 통해
public route renderer만 enqueue한다.

## 검증 기준

1. `/social/`에서 `#global-styles-inline-css`, `#wp-block-library-inline-css`,
   `#core-block-supports-inline-css`가 없다.
2. `/social/`에서 `#wp-fonts-local`, theme foundation token stylesheets,
   `axismundi-frontend.css`가 남는다.
3. normal block-theme frontend에서 Global Styles와 Block Library presentation은 유지된다.
4. wp-admin에서 `axismundi-frontend.css`가 출력되지 않는다.
5. browser console error가 없다.

## 검증 결과

2026-09-30 localhost에서 다음을 확인했다.

```text
/social/
  absent
    #global-styles-inline-css
    #wp-block-library-inline-css
    #core-block-supports-inline-css
    axismundi-navigation-icons CSS and view script
    regional Font Provider CSS

  present
    #wp-fonts-local
    wp-img-auto-sizes-contain-inline-css
    wp-emoji-styles-inline-css
    admin-bar CSS and Dashicons
    theme foundation token stylesheets
    axismundi frontend bundle

  typography observation
    #wp-fonts-local contains six theme-declared @font-face rules
    --wp--preset--font-family--roboto-flex is absent
    no Social-owned body typography rule exists yet

normal block-theme frontend
  present
    #global-styles-inline-css
    Axismundi theme style.css
    axismundi-navigation-icons CSS

wp-admin
  present
    axismundi admin bundle
  absent
    axismundi frontend bundle
```

세 surface 모두 console error 없이 로드됐다.

## 레퍼런스

- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/includes/route.php`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/docs/RESEARCH-FRONTEND-WP-GLOBAL-STYLES.md`
- `C:/Users/thaum/dev/axismundi/products/wordpress/themes/axismundi/theme.json`
