# Material Symbols icons — Scope Policy

> **Read this first before using Material Symbols in Axismundi.** The use of icon fonts in Axismundi is intentionally scoped to prevent two specific failure modes: federation breakage and content-author UX accidents.

## Files

| Style | Path | License | Axes |
|---|---|---|---|
| Outlined | `material-symbols-outlined/material-symbols-outlined.woff2` | Apache 2.0 | FILL, GRAD, opsz, wght |
| Rounded | `material-symbols-rounded/material-symbols-rounded.woff2` | Apache 2.0 | FILL, GRAD, opsz, wght |
| Sharp | `material-symbols-sharp/material-symbols-sharp.woff2` | Apache 2.0 | FILL, GRAD, opsz, wght |

All 3 styles are variable fonts with full glyph table preserved (no subset). LICENSE.txt is preserved in each subdirectory per Apache 2.0 §4(d).

## Scope policy (ALLOWED / FORBIDDEN zones)

### ALLOWED — Theme chrome and FSE template parts

Use Material Symbols in:

- `header.html`, `footer.html`, `sidebar.html` (template parts)
- Navigation blocks (FSE menu items)
- Theme switcher button (light/dark toggle)
- Search form, pagination, breadcrumbs
- Site-level UI affordances
- Editor inspector panels (when wrapped in plugin block UI)

### FORBIDDEN — Post content and federated surfaces

DO NOT use Material Symbols icon font in:

- Post content body (`post_content`)
- Comment content
- Excerpts and summaries
- `.prose`-scoped containers
- Anything that flows to ActivityPub `Note.content`
- RSS feed `<content:encoded>` and `<description>`
- Any content that may be syndicated, federated, or sanitized

### Rationale — three independent reasons

1. **Federation portability.** ActivityPub federated clients (Mastodon, Pleroma, Misskey) strip CSS classes and inline styles for security. The ligature trick (`<span>home</span>` → 🏠) requires the class to survive. When the class is stripped, the literal word "home" appears in federated timelines. This violates content portability — a core ActivityPub design value.

2. **Author UX safety.** Material Symbols rely on **ligatures**: typing "home" produces 🏠, "search" produces 🔍, etc. If the font is applied globally, content authors typing these common English words will produce icons instead of text. This is a UX disaster for any post with English vocabulary.

3. **Semantic separation.** Axismundi distinguishes between **theme chrome** (presentation; theme territory) and **content** (federation territory; plugin territory for federation logic). Mixing icon fonts into content violates this boundary.

## Implementation — scope enforcement in CSS

Theme stylesheets enforce the policy mechanically. If an icon class accidentally appears inside `.prose`, the CSS reverts the font family so that text is rendered instead of an icon glyph:

```css
/* ALLOWED — theme chrome */
.material-symbols-rounded {
  font-family: 'Material Symbols Rounded';
  font-variation-settings:
    'FILL' 0,
    'wght' 400,
    'GRAD' 0,
    'opsz' 24;
}

/* FORBIDDEN — inside post/prose content */
.prose .material-symbols-rounded,
.prose [class*="material-symbols"],
.post-content .material-symbols-rounded,
.post-content [class*="material-symbols"] {
  font-family: inherit;
  /* Render as text fallback instead of icon glyph */
}
```

`prose.css` carries the enforcement so that icons cannot leak into post body, even if a content author copies HTML from another page.

## Editor UX — block editor integration

For author-friendly icon insertion in **ALLOWED zones only**, future plugin work is planned:

- **`products/distributables/plugins/axismundi-icon-picker/`** (v3.3+) — a block variation or sidebar panel that lets authors:
  - Pick from Material Symbols catalog (Outlined / Rounded / Sharp)
  - Adjust 4 axes (FILL, GRAD, opsz, wght) per icon
  - Insert into **template parts and theme blocks only** (block context check)

Direct ligature typing in post content is not supported by Axismundi — see "Forbidden zones" above. Authors who want inline icons in content should use Image blocks with SVG, not the icon font.

## Accessibility patterns

When the icon font is used in ALLOWED zones, every instance should follow these patterns:

```html
<!-- Icon-only button: aria-label on button, aria-hidden on icon -->
<button type="button" aria-label="Open menu">
  <span class="material-symbols-rounded notranslate"
        translate="no"
        aria-hidden="true">menu</span>
</button>

<!-- Icon with visible text: aria-hidden on icon (screen readers read the text) -->
<button type="button">
  <span class="material-symbols-rounded notranslate"
        translate="no"
        aria-hidden="true">search</span>
  <span>Search</span>
</button>

<!-- Decorative icon: aria-hidden, no label needed -->
<span class="material-symbols-rounded notranslate"
      translate="no"
      aria-hidden="true">favorite</span>
```

### `translate="no"` and `notranslate` are mandatory

Google Translate and other translation tools will translate the ligature text ("home" → "집"), breaking the ligature. Both `translate="no"` (HTML5 standard) and `class="notranslate"` (Google convention) should be applied. This is non-negotiable.

## Variable axis usage — sync with Roboto Flex GRAD

Per M3 spec, GRAD axis should match between text and icons for visual harmony. Axismundi exposes a shared CSS variable (see `assets/fonts/README.md`):

```css
.material-symbols-rounded {
  font-variation-settings:
    'FILL' 0,
    'wght' var(--md-icon-weight, 400),
    'GRAD' var(--md-grade, 0),
    'opsz' var(--md-icon-opsz, 24);
}

[data-theme="dark"] {
  --md-grade: -25;   /* M3 spec: light icons on dark background */
}
```

State changes (hover, focus, active) can animate axes via CSS transitions:

```css
.icon-button:hover .material-symbols-rounded {
  --md-icon-weight: 500;
  font-variation-settings:
    'FILL' 1,                    /* fill on hover (M3 expressive state) */
    'wght' var(--md-icon-weight),
    'GRAD' var(--md-grade, 0),
    'opsz' var(--md-icon-opsz, 24);
  transition: font-variation-settings 200ms cubic-bezier(0.4, 0, 0.2, 1);
}
```

This is M3's *Expressive* state implementation — the FILL axis transition is what makes icons feel responsive in M3 motion spec.

## Choosing a style — Outlined / Rounded / Sharp

| Style | When to use |
|---|---|
| Outlined | Default, neutral, clean. Pairs well with Roboto Flex regular weight. |
| Rounded | Pairs with rounded typography (heavier weight Roboto), Korean Noto Sans KR. Matches M3 Expressive shape language. Recommended for Axismundi default. |
| Sharp | Crisp, geometric. Pairs with sharp-cornered components. Use for dense UI. |

Axismundi can ship multiple styles and let the user select via theme variation. The default style for the Korean microblog prototype is **Rounded** (visually compatible with Noto Sans KR).

## License compliance

Apache 2.0 §4(d) requires attribution in `NOTICE.md` (root). Each subdirectory's `LICENSE.txt` is preserved per §4(a). When redistributing the theme, both files must accompany the WOFF2 files.

Source: https://fonts.google.com/icons (Material Symbols family)
GitHub: https://github.com/google/material-design-icons
