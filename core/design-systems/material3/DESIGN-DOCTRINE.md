# Design Doctrine

> Why Axismundi keeps `tokens.css` — not `theme.json` — as the design Source of Truth.

This document is **not implementation notes**. It is the architectural rationale for choices that look counterintuitive from a WordPress-centric perspective. Future contributors who ask *"why isn't theme.json the authority?"* will be answered here.

---

## Doctrine 1 — `tokens.css` is the Source of Truth

The canonical design token authority for Axismundi lives in CSS, not in WordPress configuration:

```
core/design-systems/material3/runtime/tokens.css
```

This file expresses the full M3 token architecture:

- **Reference layer** (`--md-ref-palette-*`, `--md-ref-typeface-*`): 94 palette tones + 7 typefaces — raw values, never used directly by components
- **System layer** (`--md-sys-color-*`, `--md-sys-typescale-*`, etc.): semantic roles that reference the ref layer via `var()` chain

Dark mode is a **single-selector concern** in this file:

```css
[data-theme="dark"] {
  --md-sys-color-primary: var(--md-ref-palette-primary-80);
  /* 36 sys-color tokens redefined */
}
```

This is the architecture M3 specifies. We implement it faithfully.

---

## Doctrine 2 — `theme.json` is an ingestion layer, not the authority

WordPress's theme.json is a **registry** that announces presets to the editor inspector and emits `--wp--preset--*` custom properties. It is not a design system in itself.

Axismundi treats `theme.json` as a thin adapter:

```json
{
  "slug": "primary",
  "name": "Primary",
  "color": "var(--md-sys-color-primary)"
}
```

The `var()` chain delegates resolution to `tokens.css`. WordPress sees a slug; CSS resolves the value at runtime.

**This is intentionally one-way**. `tokens.css` defines the system; `theme.json` exposes a subset of slugs to WP. If a token changes, it changes in `tokens.css`. The reverse — editing `theme.json` to alter palette values — is not part of the design flow.

---

## Doctrine 3 — Modern CSS must be preserved

Tokens enable modern CSS features that static hex cannot:

```css
/* color-mix for state layers (M3 spec for hover/focus/pressed) */
.wp-block-button.is-style-filled .wp-block-button__link:hover {
  background-color: color-mix(
    in srgb,
    var(--md-sys-color-primary),
    var(--md-sys-color-on-primary) 8%
  );
}

/* Future: light-dark() for automatic scheme switching */
color: light-dark(
  var(--md-sys-color-on-surface),
  var(--md-sys-color-on-surface)  /* token chain already handles this */
);

/* Future: oklch() for perceptual color manipulation */
/* Future: color-contrast() for accessibility fallbacks */
```

If Axismundi had chosen to encode palette as static hex in `theme.json`, these would be impossible or duplicated. The `var()` chain to `tokens.css` is what makes them possible.

This is **not premature optimization**. M3's state layer specification (Material You) explicitly requires color manipulation at runtime. Static hex makes M3 partial; the var() chain makes M3 complete.

---

## Doctrine 4 — WordPress inspector GUI is secondary

Some Axismundi choices break the WordPress block inspector visual UI:

- **Color swatch shows `#000`** — WP can't reverse-resolve `var()` to hex for the picker thumbnail
- **Typography size inputs appear empty** — same reason

These are **accepted limitations**, not bugs. The inspector still functions logically (slug selection works, classes are applied, frontend renders correctly). Only the visual preview in the inspector is degraded.

The reasoning: the visual preview is a presentation layer of the editor, not the design system. We don't compromise the design system to satisfy a visual hint in the editor UI.

---

## Doctrine 5 — Plugin layer will replace limited GUI affordances

Where the inspector visual UI is inadequate, Axismundi's path is **add a better UI**, not **degrade the token system**.

Planned plugin layer:

| Plugin | Replaces |
|---|---|
| **HCT color picker panel** | WP's hex-based color picker (with HCT — Hue/Chroma/Tone — M3's native color space) |
| **Dynamic palette generator** | Static color list (seed-based 6-family × 13-tone generation per M3 Material You algorithm) |
| **Theme switcher** | Browser-only `data-theme` toggling (with palette variant selection) |
| **Typography role inspector** | WP's "Display Large 57px" dropdown (with M3 role hierarchy: display/headline/title/body/label × small/medium/large) |

Each plugin is *additive* to the WordPress inspector, not a replacement for the underlying token system. The token system stays in `tokens.css`. Plugins read it, write to it, visualize it — but never re-authoring it as hex literals.

---

## Doctrine 6 — Why Axismundi uses var() chains instead of static hex

Trade-offs between two approaches:

### Static hex in theme.json

```json
{ "slug": "primary", "color": "#6750A4" }
```

**Pros**:
- WP color picker swatch shows the correct color
- WP-native, no special configuration

**Cons**:
- Dark mode requires duplicating the entire palette as a style variation
- Palette changes require regenerating `theme.json`
- M3's HCT-based dynamic theming is impossible (would have to pre-compute every variant)
- Modern CSS (color-mix, light-dark) operates on the hex literal — no `var()` chain for runtime modulation
- Token system effectively dies; `theme.json` becomes a flat color list

### var() chain to tokens.css

```json
{ "slug": "primary", "color": "var(--md-sys-color-primary)" }
```

**Pros**:
- Dark mode is a one-line selector toggle (`[data-theme="dark"]`)
- Palette changes require editing one CSS file
- M3's full token architecture preserved (ref→sys, dual-scheme, motion, shape, elevation, state)
- Modern CSS works naturally (color-mix on tokens, light-dark, oklch)
- Token system stays a system

**Cons**:
- WP color picker swatch shows `#000` (no reverse-resolve)
- Future Axismundi plugin replaces inspector UI for color/typography

We chose the second. The cost (one degraded UI affordance) is much smaller than the cost of breaking the token system.

---

## Locked decisions (v2.1a-P0.5, do not revisit)

These were settled after multiple iterations (v1 → v2 → v3 → v4):

| Decision | State |
|---|---|
| `theme.json` palette uses `var(--md-sys-color-*)` | LOCKED |
| `theme.json` fontSizes use `var(--md-sys-typescale-*-size)` | LOCKED |
| `styles/m3-dark.json` style variation | REMOVED (tokens.css handles dark mode) |
| h1-h6 element typography in `theme.json` | REMOVED (delegated to base.css §3) |
| `block-styles.css` modern CSS (color-mix) | ALLOWED, recommended |
| `tokens.css` ref→sys 2-tier architecture | CANONICAL (matches M3 spec) |
| WP color picker swatch limitation | ACCEPTED, replaced via plugin |
| WP typography inspector input limitation | ACCEPTED, replaced via plugin |

Reverting any of these triggers the cascade explained in Doctrines 1–6. They were each tried (v2, v3, v4 iterations) and reverted. Doctrine is the record.

---

## What this means for new design systems

The same doctrine applies when adding `core/design-systems/fluent/`, `carbon/`, etc.:

1. The design system's own token specification lives in its `runtime/` (CSS) and `specs/` (docs)
2. `theme.json` (or equivalent platform config) becomes a thin var() chain ingestion
3. Modern CSS in `block-styles.css` (or equivalent) operates on the design system's tokens
4. Platform inspector GUI limitations are accepted; design-system-specific plugins compensate

This pattern is **portable across design systems**. M3 just happens to be the first one Axismundi implements.
