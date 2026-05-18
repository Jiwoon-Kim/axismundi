# Axismundi v3.6.0 — Ontology Theme Pilot Font Audit

Status: PASS WITH FIX
Date: 2026-05-18
Scope: Targeted pre-Phase-3 font registration audit

## 1. Scope

This audit checks font registration only. It is not the full Phase 3 acceptance pass.

Checked surfaces:

- `products/reference-implementations/axismundi-pilot/theme.json`
- `products/reference-implementations/axismundi-pilot/functions.php`
- `products/reference-implementations/axismundi-pilot/assets/styles/fonts.css`
- `products/reference-implementations/axismundi-lab/stylesheets/fonts.css`
- `wp-env` front-end runtime at `http://localhost:8888/`

## 2. Findings

### P2 — Serif family was exposed in `theme.json` without matching self-hosted font faces

Before this audit:

- `theme.json` exposed a `serif` preset using `"Noto Serif KR", Georgia, serif`.
- `fonts.css` did not declare `@font-face` for `Noto Serif KR`.
- `tokens.css` canonical serif stack is `'Roboto Serif', 'Noto Serif KR', Georgia, serif`.

Impact:

- Gutenberg could expose the Serif preset, but the front end/editor could fall back to system/Georgia instead of Axismundi's self-hosted serif assets.
- Korean serif coverage was not fully wired even though the font files were already copied into the Pilot.

Resolution:

- Added `Roboto Serif` and `Noto Serif KR` `@font-face` declarations to source `fonts.css`.
- Rebuilt Pilot assets with `tools/generators/build_pilot_assets.py`.
- Updated `theme.json` serif preset to `"Roboto Serif", "Noto Serif KR", Georgia, serif`.

### P2 — `theme.json` font families lacked `fontFace.src`

Follow-up review against the WordPress Theme Handbook and Font Library dev note found that the previous Pilot state still depended on `fonts.css` for loading, while `theme.json` exposed font families without `fontFace.src`.

Resolution:

- Replaced semantic `Body` / `Serif` / `Mono` UI presets with font-name presets:
  - `Roboto Flex`
  - `Noto Sans KR`
  - `Roboto Serif`
  - `Noto Serif KR`
  - `Roboto Mono`
- Added `fontFace` objects with `src: ["file:./assets/fonts/..."]` to every content font family.
- Registered an Axismundi Font Library collection via `wp_register_font_collection()` using the WordPress 6.5+ `font_family_settings` structure.
- Kept `Material Symbols Rounded` out of content font presets and the Font Library collection because it is a chrome/icon font, not prose typography.

## 3. Registration Model

Axismundi Pilot uses:

- `theme.json` `settings.typography.fontFamilies` for Gutenberg preset visibility and theme-bundled `fontFace.src` registration.
- `wp_register_font_collection()` for Font Library collection visibility through `font_family_settings`.
- `fonts.css` for actual self-hosted `@font-face` loading.
- `functions.php` `add_editor_style()` and front-end enqueue order to load `fonts.css` before token/component/block/prose styles.

Content font presets:

```txt
roboto-flex   = "Roboto Flex", "Noto Sans KR", system-ui, sans-serif
noto-sans-kr  = "Noto Sans KR", sans-serif
roboto-serif  = "Roboto Serif", "Noto Serif KR", Georgia, serif
noto-serif-kr = "Noto Serif KR", serif
roboto-mono   = "Roboto Mono", monospace
```

Chrome/icon font:

```txt
Material Symbols Rounded = loaded by fonts.css, intentionally not exposed as a content font picker option.
```

## 4. Font Face Coverage

Runtime `fonts.css` declares:

```txt
Roboto Flex              font-display: swap
Noto Sans KR             font-display: swap
Roboto Serif             font-display: swap
Noto Serif KR            font-display: swap
Roboto Mono              font-display: swap
Roboto Mono Italic       font-display: swap
Material Symbols Rounded font-display: block
```

Noto Korean fonts use Hangul unicode ranges so Korean glyphs resolve to Noto while Latin glyphs fall through to the base Roboto families.

## 5. Runtime Verification

Direct asset URL check:

```txt
200 /assets/fonts/roboto-flex/axismundi-roboto-flex.woff2
200 /assets/fonts/noto-sans-kr/axismundi-noto-sans-kr.woff2
200 /assets/fonts/roboto-serif/axismundi-roboto-serif.woff2
200 /assets/fonts/noto-serif-kr/axismundi-noto-serif-kr.woff2
200 /assets/fonts/roboto-mono/axismundi-roboto-mono.woff2
200 /assets/icons/material-symbols-rounded/material-symbols-rounded.woff2
```

Browser `document.fonts.load()` check:

```txt
Roboto Flex              loaded
Noto Sans KR             loaded
Roboto Serif             loaded
Noto Serif KR            loaded
Roboto Mono              loaded
Material Symbols Rounded loaded
```

After adding `theme.json` `fontFace.src`, text fonts are declared by both WordPress theme JSON output and Axismundi `fonts.css`. Browser checks therefore return two matching faces for text families and one for `Material Symbols Rounded`. The network layer still resolves each font URL successfully. Phase 3 can decide whether to optimize the Pilot by splitting WP theme font-face output from lab/styleguide font loading.

WordPress theme settings check:

```txt
roboto-flex|Roboto Flex|faces=1
  src=file:./assets/fonts/roboto-flex/axismundi-roboto-flex.woff2
noto-sans-kr|Noto Sans KR|faces=1
  src=file:./assets/fonts/noto-sans-kr/axismundi-noto-sans-kr.woff2
roboto-serif|Roboto Serif|faces=1
  src=file:./assets/fonts/roboto-serif/axismundi-roboto-serif.woff2
noto-serif-kr|Noto Serif KR|faces=1
  src=file:./assets/fonts/noto-serif-kr/axismundi-noto-serif-kr.woff2
roboto-mono|Roboto Mono|faces=2
  src=file:./assets/fonts/roboto-mono/axismundi-roboto-mono.woff2
  src=file:./assets/fonts/roboto-mono/axismundi-roboto-mono-italic.woff2
```

Font Library collection check:

```txt
collection=yes
name=Axismundi Pilot Fonts
roboto-flex|Roboto Flex|faces=1
noto-sans-kr|Noto Sans KR|faces=1
roboto-serif|Roboto Serif|faces=1
noto-serif-kr|Noto Serif KR|faces=1
roboto-mono|Roboto Mono|faces=2
```

Computed front-end check:

```txt
body  = "Roboto Flex", "Noto Sans KR", system-ui, sans-serif
serif = "Roboto Serif", "Noto Serif KR", Georgia, serif
```

## 6. Phase 3 Carry-Forward

PASS:

- Self-hosted font files exist.
- `fonts.css` paths are Pilot-local.
- `theme.json` font families include `fontFace.src`.
- Font Library collection uses `font_family_settings`.
- Front-end and editor styles both load `fonts.css`.
- Font files are reachable over `wp-env`.
- `document.fonts.load()` confirms all target faces.

P3 design question:

- Gutenberg now exposes font-name presets rather than semantic `Body` / `Serif` / `Mono` presets.
- `Roboto Flex` and `Roboto Serif` retain Korean fallback stacks (`Noto Sans KR` / `Noto Serif KR`) for normal theme usage.
- `Noto Sans KR` and `Noto Serif KR` are also available as explicit font-name presets for editor users who want direct Korean-family selection.
- `Material Symbols Rounded` remains chrome-only and is intentionally not exposed as a content font.

## 7. Verification Commands

```powershell
python .\tools\generators\build_pilot_assets.py
wp-env run cli wp eval '$theme_json = WP_Theme_JSON_Resolver::get_theme_data(); $settings = $theme_json->get_settings(); foreach (($settings["typography"]["fontFamilies"]["theme"] ?? array()) as $font) { echo ($font["slug"] ?? "") . "=" . ($font["fontFamily"] ?? "") . PHP_EOL; }'
wp-env run cli wp eval '$collection = WP_Font_Library::get_instance()->get_font_collection("axismundi-pilot-fonts"); echo $collection ? "collection=yes" : "collection=no";'
python .\tools\validators\validate_theme_pilot.py
npm test
```
