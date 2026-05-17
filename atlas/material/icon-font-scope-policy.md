---
rule_id: icon-font-scope-policy
domain: material/icons
captured: 2026-05-13
status: validated
source:
  - https://m3.material.io/styles/icons/overview
  - Axismundi axis-grade sync experiment
  - WordPress federation portability constraint
related:
  - material/text-fields-spec
  - wordpress/block-authoring/block-styles-registration
  - constitution-article-11 (Design Doctrine delegation)
---

# Material Symbols icon font — scope policy

## WHEN

A WordPress block theme uses Material Symbols icon font (Apache 2.0, variable, 4-axis: FILL, GRAD, opsz, wght) for theme chrome and considers using icons inside post content.

## THEN

The icon font MUST be scoped to **theme chrome zones only**. It MUST NOT be applied inside post content, prose, comments, or any surface that flows to federation/syndication.

### Three independent failure modes if violated

1. **Federation portability failure** — ActivityPub clients strip CSS classes and styles during sanitization. The ligature mechanism (`<span class="material-symbols-rounded">home</span>` → 🏠) requires the class to survive. Stripped classes leave literal "home" text in federated timelines. This violates ActivityPub content portability per W3C spec.

2. **Author UX failure** — Material Symbols ligatures fire on common English words (home, search, menu, favorite, share, more, etc.). If applied globally, content authors typing these words produce icons instead of text. Cannot be recovered without disabling the font.

3. **Semantic boundary failure** — Theme chrome is presentation (theme territory); content is federation territory (plugin territory for federation logic). Mixing icon fonts violates this layered architecture per Axismundi Constitution Article 2 (Platform ≠ Design system ≠ Federation).

## Allowed zones

- FSE template parts: `header.html`, `footer.html`, `sidebar.html`
- Navigation blocks
- Theme-level UI: theme switcher, pagination, search form, breadcrumb
- Editor inspector panels (when wrapped in plugin block UI)
- Custom blocks in `products/distributables/plugins/` (with explicit plugin context check)

## Forbidden zones

- `post_content` body
- Comment content
- Excerpts
- `.prose`-scoped containers
- RSS feed content (`<content:encoded>`, `<description>`)
- ActivityPub `Note.content`
- Anything that may be syndicated, federated, or sanitized

## Enforcement mechanism

CSS-level enforcement in `prose.css`:

```css
.prose .material-symbols-rounded,
.prose [class*="material-symbols"],
.post-content .material-symbols-rounded,
.post-content [class*="material-symbols"] {
  font-family: inherit;
}
```

If an icon class accidentally appears inside `.prose` (e.g. copied HTML), the CSS reverts the font family so the literal word is rendered as text instead of an icon glyph. This is graceful degradation — the content still makes sense.

## Required HTML attributes

Every `material-symbols-*` usage MUST include both:

- `translate="no"` (HTML5 standard, prevents Google Translate from translating ligature text)
- `class="notranslate"` (Google Translate convention)
- `aria-hidden="true"` on the icon glyph (icon-only buttons need `aria-label` on the parent button)

Both translate attributes are required because not all translation tools respect the same convention.

## Block editor UX implication

Authors should NOT be able to insert Material Symbols ligatures directly into post content. The block editor variation/picker UI (planned: `products/distributables/plugins/axismundi-icon-picker/`) MUST:

1. Check block context — only available in theme template parts and navigation blocks, not in post content area
2. Use the appropriate variable axes (FILL, GRAD, opsz, wght) via inspector controls
3. Insert the icon with full required attributes (`translate="no"` + `notranslate` + `aria-hidden`)

Direct ligature typing in post body is intentionally unsupported. Authors who want inline icons in post content should use Image blocks with SVG (which survive federation).

## Axis sync with text — M3 spec implementation

Material Symbols and Roboto Flex both have a `GRAD` axis. Per M3 spec ("Match grade levels between text and symbols for a harmonious visual effect"), GRAD should sync between text and icons. Axismundi implements this via a shared CSS custom property:

```css
:root { --md-grade: 0; }
[data-theme="dark"] { --md-grade: -25; }  /* M3: light icons on dark bg */

.t-body-large { font-variation-settings: 'GRAD' var(--md-grade); }
.material-symbols-rounded {
  font-variation-settings:
    'FILL' 0, 'wght' 400, 'opsz' 24,
    'GRAD' var(--md-grade);
}
```

This is the only known WordPress theme implementation of M3 grade-sync, made possible by self-hosted variable fonts (CDN icon font would not support custom GRAD via CSS variables).

## Choosing a style

Material Symbols ships in three styles. For Korean-first microblog (Axismundi target):

- **Rounded** — pairs with Noto Sans KR's rounded counter forms, M3 Expressive shape language. Recommended default.
- **Outlined** — neutral, dense UI fallback.
- **Sharp** — geometric, use for dense admin/utility surfaces.

## Source

- M3 spec: https://m3.material.io/styles/icons/overview
- ActivityPub content portability: https://www.w3.org/TR/activitypub/
- Axismundi Constitution Article 2 (axis separation)
- Axismundi DESIGN-DOCTRINE Doctrine 5 (plugin layer replaces inspector affordances)
