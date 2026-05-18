# Axismundi fonts

Self-hosted variable fonts under OFL 1.1.

## Strategy (v3.2.3): Roboto base + Noto CJK fallback

```
Roboto Flex / Serif / Mono   →   Non-CJK base layer (full coverage)
Noto Sans / Serif KR         →   Korean (Hangul) fallback layer
```

**Why this split.** Roboto covers Latin + Latin Extended + Greek + Cyrillic + currency symbols (₩, ¥, £, €) + arrows + math operators + general punctuation in its native glyph table (826 glyphs). Noto covers Korean. Together they handle Korean-first microblog content perfectly, and the architecture extends cleanly to Japanese / Chinese by adding more Noto CJK fonts to the fallback layer without restructuring.

The earlier v3.2.0 approach (Latin subset for Roboto) dropped ₩, ←→, ≈≠≤≥, ★, and other characters that real Korean pages need. v3.2.3 corrects this by preserving Roboto's full glyph table.

## Font family table

| Family | Path | License | Coverage | Variable axes |
|---|---|---|---|---|
| Roboto Flex | `roboto-flex/axismundi-roboto-flex.woff2` | OFL 1.1 | 826 glyphs (Latin + Cyrillic + Greek + symbols) | 13 (opsz, wght, wdth, slnt, GRAD, XOPQ, YOPQ, XTRA, YTUC, YTLC, YTAS, YTDE, YTFI) |
| Roboto Serif | `roboto-serif/axismundi-roboto-serif.woff2` | OFL 1.1 | 919 glyphs (full) | opsz, wght, wdth, GRAD |
| Roboto Mono | `roboto-mono/axismundi-roboto-mono.woff2` + italic | OFL 1.1 | 876 glyphs (full) | wght |
| Noto Sans KR | `noto-sans-kr/axismundi-noto-sans-kr.woff2` | OFL 1.1 | Korean subset (Hangul + Jamo) via unicode-range | wght |
| Noto Serif KR | `noto-serif-kr/axismundi-noto-serif-kr.woff2` | OFL 1.1 | Korean subset (Hangul + Jamo) via unicode-range | wght |

## @font-face example (current)

```css
/* Roboto Flex — no unicode-range; base layer for everything non-CJK */
@font-face {
  font-family: 'Roboto Flex';
  src: url('./fonts/roboto-flex/axismundi-roboto-flex.woff2') format('woff2-variations'),
       url('./fonts/roboto-flex/axismundi-roboto-flex.woff2') format('woff2');
  font-weight: 100 1000;
  font-stretch: 25% 151%;
  font-style: oblique -10deg 0deg;
  font-display: swap;
}

/* Noto Sans KR — unicode-range scopes to Korean only; Latin falls through */
@font-face {
  font-family: 'Noto Sans KR';
  src: url('./fonts/noto-sans-kr/axismundi-noto-sans-kr.woff2') format('woff2-variations'),
       url('./fonts/noto-sans-kr/axismundi-noto-sans-kr.woff2') format('woff2');
  font-weight: 100 900;
  font-display: swap;
  unicode-range: U+AC00-D7A3, U+1100-11FF, U+3130-318F, U+A960-A97F, U+D7B0-D7FF;
}
```

## CSS usage

```css
:root {
  --md-ref-typeface-brand:
    'Roboto Flex',
    'Noto Sans KR',                  /* Korean fallback */
    -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
```

Hangul characters render through Noto Sans KR (matched by `unicode-range`), everything else renders through Roboto Flex.

## Future expansion path

For Japanese / Chinese support, **users add fonts through WordPress's official Google Fonts integration** (WP Font Library, 6.5+) or by uploading their own. Axismundi does not need to ship every CJK font — the architecture supports user-managed additions:

```css
/* User-added fonts (via WP Font Library) extend the existing fallback chain */
font-family:
  'Roboto Flex',                                    /* Axismundi base */
  'Noto Sans KR',                                   /* Axismundi-shipped Korean */
  'Noto Sans JP', 'Noto Sans SC', 'Noto Sans TC',   /* user-added if needed */
  sans-serif;
```

Trade-off: user-added fonts will not be subset-optimized the way Axismundi's shipped fonts are (Korean Hangul-range subset, etc.) — they come full from Google Fonts CDN or upload. This is the cost of letting the user own the expansion.

No restructuring of Axismundi is required when users add CJK fonts. Roboto stays as the non-CJK base; whatever the user adds is just another fallback layer.

## Loading strategy

Preload critical fonts in `<head>` for first-paint performance:

```html
<link rel="preload"
      href="/wp-content/themes/axismundi/assets/fonts/roboto-flex/axismundi-roboto-flex.woff2"
      as="font" type="font/woff2" crossorigin>

<link rel="preload"
      href="/wp-content/themes/axismundi/assets/fonts/noto-sans-kr/axismundi-noto-sans-kr.woff2"
      as="font" type="font/woff2" crossorigin>
```

## Variable axis usage — Roboto Flex GRAD sync with Material Symbols

Both Roboto Flex (text) and Material Symbols (icons) have a GRAD axis. Per M3 spec, matching grade between text and icons creates harmonious visual rhythm. Axismundi exposes a single CSS custom property to sync them — see `assets/icons/README.md` for details.

## License compliance

Each subdirectory contains:

- **`OFL.txt`** — verbatim SIL Open Font License 1.1 from Google Fonts. **Do not remove.**
- **`source.txt`** — provenance (URL, original filename, conversion method, date)

The `axismundi-` filename prefix indicates the file was processed (TTF → WOFF2 format conversion). For Roboto, no glyph subsetting was applied. For Noto, format conversion only — `unicode-range` in CSS scopes the font to Korean glyphs, but the WOFF2 file itself contains the full Noto KR glyph table.

CSS `font-family` references use the original font names (`'Roboto Flex'`, `'Noto Sans KR'`, etc.) per OFL 1.1's Reserved Font Name policy. The fonts are unaltered in glyph design; only format was converted.
