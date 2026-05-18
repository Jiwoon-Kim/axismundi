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

## 2. Finding

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

## 3. Registration Model

Axismundi Pilot uses:

- `theme.json` `settings.typography.fontFamilies` for Gutenberg preset visibility.
- `fonts.css` for actual self-hosted `@font-face` loading.
- `functions.php` `add_editor_style()` and front-end enqueue order to load `fonts.css` before token/component/block/prose styles.

Content font presets:

```txt
body  = "Roboto Flex", "Noto Sans KR", system-ui, sans-serif
serif = "Roboto Serif", "Noto Serif KR", Georgia, serif
mono  = "Roboto Mono", monospace
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

WordPress theme settings check:

```txt
body="Roboto Flex", "Noto Sans KR", system-ui, sans-serif
serif="Roboto Serif", "Noto Serif KR", Georgia, serif
mono="Roboto Mono", monospace
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
- Front-end and editor styles both load `fonts.css`.
- Font files are reachable over `wp-env`.
- `document.fonts.load()` confirms all target faces.

P3 design question:

- Gutenberg picker currently exposes semantic presets (`Body`, `Serif`, `Mono`), not separate `Noto Sans KR` / `Noto Serif KR` entries.
- This is acceptable for the Pilot because Korean fonts are fallback layers inside the semantic stacks.
- If the desired editor UX is explicit Korean family selection, add a future typography preset decision rather than patching it silently.

## 7. Verification Commands

```powershell
python .\tools\generators\build_pilot_assets.py
wp-env run cli wp eval '$theme_json = WP_Theme_JSON_Resolver::get_theme_data(); $settings = $theme_json->get_settings(); foreach (($settings["typography"]["fontFamilies"]["theme"] ?? array()) as $font) { echo ($font["slug"] ?? "") . "=" . ($font["fontFamily"] ?? "") . PHP_EOL; }'
python .\tools\validators\validate_theme_pilot.py
npm test
```
