# Omphalos — `/embed/` template route (the provider-side embed card)

> **Purpose**: cut the route for the **WordPress embed TEMPLATE** — the card our OWN
> posts render as when they are embedded on another WordPress site (or self-embedded
> here) via the `/embed/` oEmbed endpoint. This is the OTHER half of the embeds work:
> the `core/embed` BLOCK lane (`docs/EMBEDS-ROUTE.md`) is the CONSUMER side (external
> content inside our post); THIS is the PROVIDER side (our content as an object card).
> **Status**: route + CSS-0 diagnostic + **compatibility skin IMPLEMENTED** — the
> theme owns a narrow `embed-content.php` copy so the extension point is explicit,
> but the markup remains core-faithful. `embed.css` deliberately skins ONLY
> theme-aware colour + font-family; core keeps layout, spacing, image branch,
> headline scale, excerpt rhythm, footer layout, and the 200px iframe floor.
> Enqueued via `enqueue_embed_scripts` (depends on core `wp-embed-template`); dark
> via `prefers-color-scheme`. The previous proportional/card redesign
> (title-large, full-bleed rectangular hero, no-image rail, post-only date meta) was
> rolled back because square/no-image cards read worse than the core template.
> **LANE PARKED here** — article embed is a compatibility surface with Omphalos
> colour/font only; attachment (2b/2c) + activity variants wait on the
> attachment-page / metadata work, not entered yet.
> **VQA**: dedicated page `/vqa-embed-template/` (seeded by
> `scripts/seed-vqa-embed-template.php`) SELF-EMBEDS 5 specimens — post/page ×
> featured-image shape (square float / rectangular hero / none) — so each renders
> the real `/embed/` card; post & page share one article card, meta is the only
> per-type difference to observe. (Kept separate from `/vqa-embeds/`, which is the
> consumer block lane.) 404/error + attachment variants are out of this first VQA.
> **Date**: 2026-06-02 · WP 7.0 · M3 Expressive.

---

> **READING NOTE**: §§1–§10 below are the EXPLORATION RECORD (diagnosis + the fuller
> object-card direction that was explored and then narrowed). The authoritative,
> shipped outcome is **§11 — FINAL STATE (compatibility skin)**. Where the body and
> §11 disagree, §11 wins.

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
wordpress.org's rules). When we PROVIDE our embed we set the policy — and our
provided card is an **OBJECT CARD system** (§10.1), not merely a post card:
`article` is only its first variant.

---

## §2 — Diagnostic: the core default `/embed/` DOM (CSS-0)

Demo post `/?p=47&embed=true` (`vqa-demo-m3-bridge`), featured image = mogu
1024×768 (ratio 1.333). The core template
(`/var/www/html/wp-includes/theme-compat/embed-content.php`) renders:

```txt
div.wp-embed (+ post-class)
  p.wp-embed-heading > a                 ← headline link (core class name)
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
  the headline (core's media-above treatment).
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
card (headline / square thumbnail float / excerpt / footer with site-icon + comments +
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

**(SHIPPED, see §11):** the skin uses `var(--token, fallback)` ALONE — no `@supports`
two-layer, and **no token CSS / `@font-face` is enqueued** into the iframe. The
explicit fallbacks render as WP-core / M3-flavoured values, and a host that already
carries the sys tokens picks them up. The `@supports` + token-enqueue approach above
was an explored alternative that was NOT taken (too heavy for a portable surface).

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
--md-sys-typescale-title-medium-*  (headline, if a future owned variant opts in)
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
  legible.

---

## §9 — Explicitly NOT in this step

- No CSS / hooks yet (this doc is the diagnostic baseline).
- No parent↔iframe scheme synchronisation (out of WP-to-WP standard compat).
- No `--comp-*` dependency in the embed layer.
- No change to the consumer `core/embed` BLOCK lane (EMBEDS-ROUTE.md).

---

## §10 — Locked pre-implementation decisions (before any `embed.css`)

The layout build is on HOLD (provider experiments in the consumer lane come first).
These three are fixed now so the first cut does not close the surface as a mere
"post card".

### 10.1 — `/embed/` is an OBJECT CARD surface, not a Post Card
The core DOM is post-shaped, but Omphalos/Axismundi treat `/embed/` as an Object
Card SYSTEM; the WordPress post embed is just the `article` variant. Keep the shell
+ variants open:

```txt
article / post            ← first variant (this lane's first cut)
note                      (short, ActivityPub-style)
attachment:image          ← Omphalos attachment-page family
attachment:video
attachment:audio
activity:like             ← actor + "liked" + object preview   (needs AP bridge)
activity:announce (boost) ← actor + "boosted" + object card     (needs AP bridge)
```

Split of ownership: the THEME owns the card shell + variant LAYOUT + M3 tokens +
media rendering; `object_type` / `activity_type` / actor·object metadata come from
the **ActivityPub plugin/bridge** (it injects a class/context, e.g.
`.ax-embed--object-note`). Objects (article / note / attachment) are partly
theme-doable via `get_post_type()` / MIME / featured image; activities (like / boost
/ reply) need the AP data bridge (actor, object, target, published, attribution).
Design the shell to RECEIVE variants — do not hardcode "post card".

**NOT the ActivityPub s2s render path (correction).** `/embed/` is the WP-to-WP /
web **iframe card our content is CONSUMED as** — a presentation surface. It is NOT
ActivityPub server-to-server rendering, which is a SEPARATE lane: receive remote AP
**JSON-LD** → our server interprets the object/activity → render local HTML. The two
only SHARE the card **ONTOLOGY** (article / note / attachment / like / boost
layouts); the implementation SURFACES differ (iframe embed template vs a JSON-LD
renderer). So reuse the variant-layout system across both, but keep the lanes
distinct — earlier notes over-coupled `/embed/` to AP s2s.

**Visual directions explored** (design candidates, NOT the default — prototyped in a
scratch lab): *Hero* (full-bleed 16:9 media band + title-large), *Split* (media rail
beside a flex body), *Editorial* (overlaid headline on a scrim). They are too strong
/ too divergent from core's square-float ontology to be the WP-to-WP DEFAULT. A
shape-adaptive trial (rectangular hero, square core float, no-image rail) was also
tested and rolled back: rectangular improved, but square/no-image became worse than
core. Keep these as future Object/Activity Card variants, not the current
compatibility embed template.

### 10.2 — Typography consumes the existing typescale contract
No new embed-only type. Same rule as the collection lane:

```txt
owned object-card variant → attach t-* utility classes
                            (t-title-medium / t-body-medium / t-body-small)
compatibility embed skin  → leave core headline/excerpt/footer scale intact;
                            set colour + font-family only
untouched core template   → selector-based utility EQUIVALENT using --md-sys-typescale-*
font-family NAMES may be declared; @font-face is NOT enqueued into the embed iframe
```

Declaring brand font NAMES is compatibility-safe: with no `@font-face` there is no
download, and an absent font simply falls through. Korean falls back Noto Sans KR →
system. Canonical stack:

```css
font-family: var(--md-sys-typescale-body-medium-font,
  "Roboto Flex", "Noto Sans KR", system-ui, -apple-system, BlinkMacSystemFont,
  "Segoe UI", sans-serif);
```

### 10.3 — Token loading: self-contained first (A), embed-safe subset later (B)
The embed doc is isolated. The FIRST cut is **A**: `embed.css` is SELF-CONTAINED —
`var(--md-sys-*, <core-compatible fallback>)` everywhere; do NOT enqueue the full
`tokens.ref/sys.*` into the iframe (too heavy for a portable compat surface). Dark
gets a `prefers-color-scheme` fallback so it works even with no token layer:

```css
@media (prefers-color-scheme: dark) {
  .wp-embed {
    background:   var(--md-sys-color-surface-container-low, #1d1b20);
    color:        var(--md-sys-color-on-surface, #e6e0e9);
    border-color: var(--md-sys-color-outline-variant, #49454f);
    color-scheme: dark;
  }
}
```

LATER = **B**: a small shared `embed.tokens.css` (color / shape / space / typescale
subset) for Omphalos ↔ future Axismundi theme. Never depend on `--comp-*` in the
embed layer.

---

## §11 — FINAL STATE (lane closed) — the compatibility skin

After exploring the fuller object-card direction (§3–§10 + a scratch-lab proportional
redesign), the lane was deliberately narrowed and CLOSED here. **§§1–§10 are the
EXPLORATION RECORD; this section is the authoritative outcome.**

**Shipped** — `/embed/` is a COMPATIBILITY SKIN, not a redesigned card:
- `embed-content.php` is owned but **core-faithful**: a narrow seam only. The markup,
  hooks, shape branch, footer, and meta are core's — NO `t-*` classes, NO state
  classes, NO per-type (post/page) meta.
- `embed.css` (enqueued via `enqueue_embed_scripts`, after `wp-embed-template`) skins
  **only** theme-aware COLOUR + FONT-FAMILY + card/image RADIUS. Core keeps the
  layout, spacing, headline/excerpt/footer scale, the shape branch, and the 200px
  iframe-height floor.
- Self-contained `var(--md-sys-*, <WP-core fallback>)`; **no token CSS and no
  `@font-face` enqueued** into the iframe. Dark via the iframe's own
  `prefers-color-scheme` with M3-dark fallbacks; no parent-scheme sync.

**Why so conservative**: the card is a document that travels off-site (WP-to-WP) and
must read in any host/theme and with no token layer. Conservative LAYOUT + progressive
COLOUR/FONT compatibility beats an over-closed redesign here. A scratch-lab v2
(title-large, full-bleed rectangular hero, corner-large, no-image rail, post-only
date) was built and ROLLED BACK — rectangular improved, but square / no-image read
worse than the core template.

**Deferred — keep `embed-content.php` as the future variant seam**:
- attachment `image / video / audio` embed variants (wordpress.org plugin/theme pages
  render differently) — the most likely next reason to re-open ownership;
- object / activity card variants (note / like / boost) + the ActivityPub bridge;
- `t-*` utility attachment, post/page meta split, hero/split/editorial treatments,
  token enqueue (`embed.tokens.css` subset).

**Rule**: `embed-content.php` stays core-faithful — **no structural change** until a
real variant needs it; diff it against core's `theme-compat/embed-content.php` on WP
core updates.
