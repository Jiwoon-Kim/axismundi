# Draft — WordPress Playground bug: core icon SVGs missing on the first request

> 상태: **게시됨** 2026-09-14,
> [WordPress/wordpress-playground#4326](https://github.com/WordPress/wordpress-playground/issues/4326).
> API로 올려 템플릿 라벨(`[Type] Bug`)은 붙지 않았다 — 유지관리자가 분류. 본문은 아래 Title 이후와 같다(8,029자).
>
> 올린 곳: WordPress/wordpress-playground › Issues, 🐞 Bug Report 템플릿 항목 순서(`1-bug-report.yml`).
> PR은 나중에 요청이 오면 웹 편집기로. 수정은 Dockerfile 예외 한 줄이라 본문에 diff로 넣었다.
>
> 확인한 사실(2026-09-14):
> - WordPress 7.1 `icons.php:101` 아이콘 폴더 `ABSPATH . WPINC . '/images/icon-library/'`, 매니페스트 88개, `core/info` = `info.svg`.
>   `class-wp-icons-registry.php:276-295` `get_content()`가 `realpath` → `.svg` 확인 → `is_readable` → `file_get_contents`.
> - Playground `packages/playground/wordpress-builds/build/Dockerfile:65-81`이 `*.svg`를 지우고 예외는
>   `wp-admin/images/*.svg`, `wp-includes/fonts/dashicons.svg`(#729), `wp-includes/images/media/*`(#770)뿐. 마지막 수정 `c452eba5d2`(2026-07-06).
> - `remote/src/lib/worker-utils.ts`: 빠진 정적 파일 backfill은 "after the Playground boots … and the first page is rendered".
> - 공개 빌드: `wp-7.0` icon-library 331 svg, `wp-7.1` 88 svg, `wp-beta`는 `images/icon-library` 331 + `icons/library` 332
>   (`icons/library`를 읽는 PHP는 확인 못 함 → 본문엔 참고로만).
> - 중복 검색(`icon-library`, `wp_get_icon`, `icon registry`): 해당 이슈·PR 없음.
> - **실측 재현**(이 PC 브라우저, playground.wordpress.net, WP 7.1 / PHP 8.3): 첫 로드 `missing · empty`, `core/icon` 출력 없음 →
>   안쪽 iframe 새로고침 후 `present · SVG`, `<div class="wp-block-icon"><svg …>`. 대조: wp-env(전체 WordPress)는 `present · SVG`.
>
> 게시 전: 링크를 한 번 더 열어 첫 로드가 여전히 비는지 확인(Playground 배포로 고쳐졌을 수 있음).

---

## Title

Core icons render empty on the first request: icon-library SVGs are stripped from the minified build

## Prerequisites

- [x] I have carried out troubleshooting steps and I believe I have found a bug.
- [x] I have searched for similar bugs in both open and closed issues and cannot find a duplicate.

## Describe the bug

WordPress 7.0+ reads core icon SVGs from disk in PHP. `_wp_register_default_icons()` registers each icon with a `file_path` under `wp-includes/images/icon-library/`, and `WP_Icons_Registry::get_content()` returns empty unless that file `is_readable()`.

The minified build removes `*.svg` as static files not used by PHP (`packages/playground/wordpress-builds/build/Dockerfile`, "Remove static files not used by PHP"). The existing exceptions cover `wp-admin/images/*.svg`, `wp-includes/fonts/dashicons.svg` (#729) and `wp-includes/images/media/*` (#770), but not `wp-includes/images/icon-library/`.

Those files come back only when the static assets are backfilled, which starts after the first page is rendered. So the first request that renders an icon gets nothing, and a reload works.

This affects `core/icon`, `wp_get_icon()` and anything built on the Icon Registry — including block plugins' WordPress.org Live Previews, whose landing page is usually the first request.

## Expected behavior

`core/icon` with `core/info` renders its SVG on the first request, as it does in a full WordPress install.

## Actual behavior

On the first request `wp-includes/images/icon-library/info.svg` is not on disk, `wp_get_icon( 'core/info' )` returns an empty string, and the Icon block renders nothing. After reloading the page the file is present and the block renders `<div class="wp-block-icon"><svg …>`.

Measured on playground.wordpress.net with WordPress 7.1 and PHP 8.3:

| | First load | After reload |
|---|---|---|
| `wp-includes/images/icon-library/info.svg` | missing | present |
| `wp_get_icon( 'core/info' )` | empty | SVG |
| Icon block output | nothing | `<div class="wp-block-icon"><svg …>` |

## Steps to reproduce

1. Open [this Blueprint](https://playground.wordpress.net/#%7B%22%24schema%22%3A%22https%3A%2F%2Fplayground.wordpress.net%2Fblueprint-schema.json%22%2C%22landingPage%22%3A%22%2Ficon-repro%2F%22%2C%22preferredVersions%22%3A%7B%22php%22%3A%228.3%22%2C%22wp%22%3A%227.1%22%7D%2C%22steps%22%3A%5B%7B%22step%22%3A%22writeFile%22%2C%22path%22%3A%22%2Fwordpress%2Fwp-content%2Fmu-plugins%2Ficon-file-check.php%22%2C%22data%22%3A%22%3C%3Fphp%5Cn%2F%2A%2A%5Cn%20%2A%20Repro%20helper%3A%20says%2C%20on%20every%20request%2C%20whether%20the%20SVG%20behind%20core%2Finfo%20is%20on%20disk.%5Cn%20%2A%2F%5Cnadd_shortcode%28%20%27icon_file_check%27%2C%20static%20function%20%28%29%20%7B%5Cn%5Ct%24path%20%3D%20ABSPATH%20.%20WPINC%20.%20%27%2Fimages%2Ficon-library%2Finfo.svg%27%3B%5Cn%5Ct%24icon%20%3D%20function_exists%28%20%27wp_get_icon%27%20%29%20%3F%20wp_get_icon%28%20%27core%2Finfo%27%20%29%20%3A%20null%3B%5Cn%5Ctreturn%20sprintf%28%5Cn%5Ct%5Ct%27%3Cp%3E%3Ccode%3E%25s%3C%2Fcode%3E%3A%20%3Cstrong%3E%25s%3C%2Fstrong%3E%20%26middot%3B%20%3Ccode%3Ewp_get_icon%28%20%5C%5C%27core%2Finfo%5C%5C%27%20%29%3C%2Fcode%3E%3A%20%3Cstrong%3E%25s%3C%2Fstrong%3E%3C%2Fp%3E%27%2C%5Cn%5Ct%5Ctesc_html%28%20WPINC%20.%20%27%2Fimages%2Ficon-library%2Finfo.svg%27%20%29%2C%5Cn%5Ct%5Ctis_readable%28%20%24path%20%29%20%3F%20%27present%27%20%3A%20%27missing%27%2C%5Cn%5Ct%5Ctnull%20%3D%3D%3D%20%24icon%20%3F%20%27unavailable%27%20%3A%20%28%20%27%27%20%3D%3D%3D%20%28string%29%20%24icon%20%3F%20%27empty%27%20%3A%20%27SVG%27%20%29%5Cn%5Ct%29%3B%5Cn%7D%20%29%3B%5Cn%22%7D%2C%7B%22step%22%3A%22runPHP%22%2C%22code%22%3A%22%3C%3Fphp%5Cnrequire_once%20%27%2Fwordpress%2Fwp-load.php%27%3B%5Cn%24content%20%3D%20%3C%3C%3C%27HTML%27%5Cn%3C%21--%20wp%3Aparagraph%20--%3E%5Cn%3Cp%3EBelow%20is%20a%20core%20Icon%20block%20set%20to%20%3Ccode%3Ecore%2Finfo%3C%2Fcode%3E%2C%20then%20a%20check%20run%20on%20this%20request.%3C%2Fp%3E%5Cn%3C%21--%20%2Fwp%3Aparagraph%20--%3E%5Cn%5Cn%3C%21--%20wp%3Aicon%20%7B%5C%22icon%5C%22%3A%5C%22core%2Finfo%5C%22%7D%20%2F--%3E%5Cn%5Cn%3C%21--%20wp%3Ashortcode%20--%3E%5Cn%5Bicon_file_check%5D%5Cn%3C%21--%20%2Fwp%3Ashortcode%20--%3E%5Cn%5Cn%3C%21--%20wp%3Aparagraph%20--%3E%5Cn%3Cp%3EOn%20the%20first%20load%20the%20icon%20is%20blank%20and%20the%20file%20is%20missing.%20Reload%20with%20the%20Playground%20refresh%20button%3A%20the%20icon%20renders%20and%20the%20file%20is%20present.%3C%2Fp%3E%5Cn%3C%21--%20%2Fwp%3Aparagraph%20--%3E%5CnHTML%3B%5Cnwp_insert_post%28%20array%28%5Cn%5Ct%27post_type%27%20%20%20%20%3D%3E%20%27page%27%2C%5Cn%5Ct%27post_status%27%20%20%3D%3E%20%27publish%27%2C%5Cn%5Ct%27post_name%27%20%20%20%20%3D%3E%20%27icon-repro%27%2C%5Cn%5Ct%27post_title%27%20%20%20%3D%3E%20%27Core%20icon%20SVG%20on%20first%20load%27%2C%5Cn%5Ct%27post_content%27%20%3D%3E%20%24content%2C%5Cn%29%20%29%3B%5Cn%22%7D%5D%7D). It writes a small mu-plugin with an `[icon_file_check]` shortcode that reports, on each request, whether `info.svg` is on disk and what `wp_get_icon( 'core/info' )` returns, then creates a page with a `core/icon` block and that shortcode.
2. On the landing page, the icon is blank and the check reads `missing` / `empty`.
3. Reload with Playground's refresh button: the icon renders and the check reads `present` / `SVG`.

<details><summary>Blueprint</summary>

```json
{
    "$schema": "https://playground.wordpress.net/blueprint-schema.json",
    "landingPage": "/icon-repro/",
    "preferredVersions": {
        "php": "8.3",
        "wp": "7.1"
    },
    "steps": [
        {
            "step": "writeFile",
            "path": "/wordpress/wp-content/mu-plugins/icon-file-check.php",
            "data": "<?php\n/**\n * Repro helper: says, on every request, whether the SVG behind core/info is on disk.\n */\nadd_shortcode( 'icon_file_check', static function () {\n\t$path = ABSPATH . WPINC . '/images/icon-library/info.svg';\n\t$icon = function_exists( 'wp_get_icon' ) ? wp_get_icon( 'core/info' ) : null;\n\treturn sprintf(\n\t\t'<p><code>%s</code>: <strong>%s</strong> &middot; <code>wp_get_icon( \\'core/info\\' )</code>: <strong>%s</strong></p>',\n\t\tesc_html( WPINC . '/images/icon-library/info.svg' ),\n\t\tis_readable( $path ) ? 'present' : 'missing',\n\t\tnull === $icon ? 'unavailable' : ( '' === (string) $icon ? 'empty' : 'SVG' )\n\t);\n} );\n"
        },
        {
            "step": "runPHP",
            "code": "<?php\nrequire_once '/wordpress/wp-load.php';\n$content = <<<'HTML'\n<!-- wp:paragraph -->\n<p>Below is a core Icon block set to <code>core/info</code>, then a check run on this request.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:icon {\"icon\":\"core/info\"} /-->\n\n<!-- wp:shortcode -->\n[icon_file_check]\n<!-- /wp:shortcode -->\n\n<!-- wp:paragraph -->\n<p>On the first load the icon is blank and the file is missing. Reload with the Playground refresh button: the icon renders and the file is present.</p>\n<!-- /wp:paragraph -->\nHTML;\nwp_insert_post( array(\n\t'post_type'    => 'page',\n\t'post_status'  => 'publish',\n\t'post_name'    => 'icon-repro',\n\t'post_title'   => 'Core icon SVG on first load',\n\t'post_content' => $content,\n) );\n"
        }
    ]
}
```

</details>

## Isolating the problem

- [x] I have deactivated other plugins and confirmed this bug occurs when only this plugin is active.
- [x] This bug happens with a default WordPress theme active.
- [x] I can reproduce this bug consistently using the steps above.

No plugins are installed; the only addition is the diagnostic mu-plugin in the Blueprint, and the Icon block is core.

A possible fix, following #729 and #770:

```diff
     -not -path '*/wp-includes/fonts/dashicons.svg' \
     # WordPress functions like wp_mime_type_icon() use
     # the icons shipped in images/media. See #770.
     -not -path '*/wp-includes/images/media/*' \
+    # The Icon Registry reads core icon SVGs from disk (WordPress 7.0+).
+    -not -path '*/wp-includes/images/icon-library/*' \
     -delete
```

The `wp-beta` build also ships `wp-includes/icons/library/*.svg`; I have not checked whether PHP reads that directory, so it may need the same exception.
