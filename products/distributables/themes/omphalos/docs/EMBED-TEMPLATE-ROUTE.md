# Omphalos — `/embed/` template route (the provider-side embed card)

> **Purpose**: cut the route for the **WordPress embed TEMPLATE** — the card our OWN
> posts render as when they are embedded on another WordPress site (or self-embedded
> here) via the `/embed/` oEmbed endpoint. This is the OTHER half of the embeds work:
> the `core/embed` BLOCK lane (`docs/EMBEDS-ROUTE.md`) is the CONSUMER side (external
> content inside our post); THIS is the PROVIDER side (our content as an object card).
> **Status**: route + CSS-0 diagnostic of the core default embed template. **No CSS,
> no hooks wired yet.** Implementation is the NEXT step.
> **Date**: 2026-06-02 · WP 7.0 · M3 Expressive.

---

## §1 — Two embed surfaces (don't conflate them)

```txt
1. core/embed BLOCK   = external content placed INSIDE our post   → EMBEDS-ROUTE.md
                        theme owns the OUTER SHELL only; provider owns the iframe.
2. /embed/ TEMPLATE   = OUR post rendered as a card when embedded
                        on another site (or self-embedded)         → THIS doc
                        theme OWNS the whole card (it is our document inside the
                        iframe), so we can brand it / make it M3 + dark-aware.
```

The compatibility asymmetry is the whole point: when we CONSUME a third-party WP
embed we cannot normalise its interior (ma.tt's rules, wordpress.com's rules,
wordpress.org's rules). When we PROVIDE our embed we set the policy.

---

## §2 — Diagnostic: the core default `/embed/` DOM (CSS-0)

Demo post `/?p=47&embed=true` (`vqa-demo-m3-bridge`), featured image = mogu
1024×768 (ratio 1.333). The core template
(`/var/www/html/wp-includes/theme-compat/embed-content.php`) renders:

```txt
div.wp-embed (+ post-class)
  p.wp-embed-heading > a                 ← title link
  div.wp-embed-featured-image.square     ← shape branch (below)
    > a > img.attachment-medium
  div.wp-embed-excerpt > p               ← excerpt
  div.wp-embed-footer
    div.wp-embed-site-title > a
      img.wp-embed-site-icon + span      ← site icon + name ("omphalos")
    div.wp-embed-meta
      div.wp-embed-comments > a          ← comment count
      div.wp-embed-share > button.wp-embed-share-dialog-open
```

**Featured-image shape branch** (`embed-content.php` line 61):
`$shape = $w / $h >= 1.75 ? 'rectangular' : 'square';`
- `rectangular` (≥1.75, wide) → `.wp-embed-featured-image.rectangular`, shown ABOVE
  the title (hero).
- `square` (< 1.75, square-ish) → `.wp-embed-featured-image.square`, shown NEXT TO
  the content (float). (mogu 1.333 → square, as observed.)
Core comment, verbatim: *“Rectangular images are shown above the title while square
images are shown next to the content.”* Provider sites can override the shape via
`embed_thumbnail_image_shape` / the size via `embed_thumbnail_image_size`.

**CSS-0 computed (core default embed CSS, `wp-embed-template`)**:

```txt
.wp-embed  background #fff (rgb 255,255,255)   color rgb(100,105,112)
           font -apple-system… (system stack)  color-scheme: normal
heading link color rgb(44,51,56)
DARK: identical — the core embed card is LIGHT-ONLY (bg stays #fff under a dark
      colorScheme; it does NOT follow prefers-color-scheme).
```

So the card is the place to add M3 + dark; the screenshot confirms a clean white
card (title / square thumbnail float / excerpt / footer with site-icon + comments +
share) with no dark response.

---

## §3 — Policy: the `/embed/` template is a COMPATIBILITY SURFACE

This is the load-bearing decision (do NOT treat it as merely "a prettier card"):

```txt
WP embed template is a compatibility surface.

- Consuming third-party WP embeds: do NOT normalise internal layout/colour
  (that is the core/embed BLOCK lane — outer shell only).
- Providing Omphalos/Axismundi embeds: render an M3-aware card using the SHARED
  sys tokens.
- If the M3 tokens are missing, fall back to WP core-compatible colours, spacing,
  and system fonts (the card must still read).
- Dark mode follows the IFRAME DOCUMENT's own prefers-color-scheme / data-theme
  contract; NO parent synchronisation (parent theme is unknowable without
  postMessage / URL params, which leave WP-to-WP standard compat).
- The embed template must NOT depend on comp/component-only tokens unless the
  fallback is complete.
```

Rationale: the embed doc is a self-contained surface that travels to OTHER sites.
It must degrade gracefully where our token layer is absent, and it must not assume
the parent's scheme.

---

## §4 — Two-layer CSS design (core-compatible fallback + M3 token-aware)

The embed document is ISOLATED — it does NOT automatically carry the theme's token
CSS. So the embed stylesheet must inline its own fallbacks. Pattern:

```css
/* A. core-compatible fallback layer (always valid, light-safe, system font) */
.wp-embed {
  background: #fff;
  color: #1d2327;
  border-color: #dcdcde;
}

/* B. M3 token-aware layer (used when the sys tokens are present) */
@supports (color: var(--md-sys-color-surface)) {
  .wp-embed {
    background: var(--md-sys-color-surface-container-low, #fff);
    color: var(--md-sys-color-on-surface, #1d2327);
    border-color: var(--md-sys-color-outline-variant, #dcdcde);
  }
}
```

`var(--token, fallback)` alone also works, but because this surface travels off-site
the explicit fallback values are kept (belt-and-braces). The token CSS itself
(`tokens.ref/sys.*`) must be enqueued INTO the embed document (via
`enqueue_embed_scripts`) for layer B to activate.

---

## §5 — Shared sys-token contract (Omphalos ↔ future Axismundi theme)

The `/embed/` card consumes SYS tokens only (not comp), so it stays portable across
themes that share the M3 layer:

```txt
--md-sys-color-surface / -surface-container-low
--md-sys-color-on-surface / -on-surface-variant
--md-sys-color-outline-variant
--md-sys-color-primary
--md-sys-shape-corner-medium
--md-sys-typescale-title-medium-*  (heading)
--md-sys-typescale-body-medium-*   (excerpt)
--md-sys-typescale-body-small-*    (meta / site title)
--space-xs / -sm / -md
```

Card padding/radius use `--space-md` / `--md-sys-shape-corner-medium`, NOT
`--comp-card-*` — the compatibility layer must not require the comp tier.

---

## §6 — Dark mode policy

```txt
- The /embed/ iframe follows its OWN document's prefers-color-scheme / data-theme.
- If the Omphalos/Axismundi token layer is present, reuse the existing
  data-theme / auto scheme structure inside the embed doc.
- Do NOT force-sync to the parent theme (needs postMessage / URL params → leaves
  WP-to-WP standard compat; not now).
- Set `color-scheme` on the embed root so form controls / scrollbars match.
```

---

## §7 — Hook map (provider-side customisation)

```txt
embed.php / embed template hierarchy   the /embed/ screen structure (theme override)
enqueue_embed_scripts                  enqueue the embed-only stylesheet (+ tokens)
embed_head                             inline critical CSS if needed
embed_thumbnail_image_shape            force rectangular vs square (vs core 1.75 rule)
embed_thumbnail_image_size             embed-only image size
embed_content                          extra content under the excerpt (e.g. date)
embed_content_meta                     footer action area
embed_site_title_html                  site name / logo markup
```

---

## §8 — Write scope, fences, validation plan (NEXT step)

- **Write scope (planned)**: a new embed stylesheet (e.g.
  `assets/styles/embed.css`) enqueued via `enqueue_embed_scripts` in `functions.php`;
  the sys/ref token CSS enqueued into the embed doc; optionally an `embed.php` /
  partial template override ONLY if structure changes are needed (prefer CSS-only).
  No changes to `blocks.css` / the consumer lane.
- **Fences**: scope every selector under `.wp-embed` (the embed document root). Two
  layers (core fallback + `@supports` token layer). No comp-tier token without a
  complete fallback. No parent-scheme assumptions.
- **Validation**: load `/?p=47&embed=true` computed both schemes — card uses sys
  tokens when present (surface/on-surface/outline), degrades to WP core colours when
  the token CSS is removed; dark follows the embed doc's own scheme; the square
  vs rectangular branch both read; the footer (site title / comments / share) stays
  legible. Also verify a wide (≥1.75) featured image renders the rectangular hero.

---

## §9 — Explicitly NOT in this step

- No CSS / hooks yet (this doc is the diagnostic baseline).
- No parent↔iframe scheme synchronisation (out of WP-to-WP standard compat).
- No `--comp-*` dependency in the embed layer.
- No change to the consumer `core/embed` BLOCK lane (EMBEDS-ROUTE.md is closed).
