# Omphalos — core/embed route (diagnostic baseline, pre-implementation)

> **Purpose**: cut the route for the **Embeds** block category (`core/embed` and its
> provider variants) BEFORE any CSS, per the diagnostic-first lock. The theme owns
> the **outer shell only** (figure spacing, media radius/clip, caption, fallback
> card); it never reaches inside a provider iframe, script, or blockquote UI.
> **References**: WordPress [Embed blocks](https://wordpress.org/documentation/category/embed-blocks/),
> [Embed block](https://wordpress.org/documentation/article/embed-block/),
> [Embeds](https://wordpress.org/documentation/article/embeds/).
> **Status**: route + curated VQA specimen + CSS-0 diagnostic + **§17 outer-shell
> CSS implemented** (figure rhythm, wrapper radius/clip, iframe fill, fallback
> surface — verified computed both schemes). The `/embed/` TEMPLATE (when our post
> is embedded elsewhere) is a SEPARATE adjacent lane — see §9.
> **Date**: 2026-06-02 · WP 7.0 · M3 Expressive.

---

## §1 — Diagnostic: how core/embed renders (CSS-0, server output)

`core/embed` is effectively **static save()**: the block saves a
`<figure class="wp-block-embed is-type-* is-provider-*"><div class="wp-block-embed__wrapper">URL</div></figure>`,
and the front-end `the_content` **autoembed (oEmbed)** replaces the bare URL with
the provider HTML at render (cached per-post in `_oembed_*`). So the DOM shape is
**provider-dependent**, not theme-controlled.

This wp-env **does** resolve oEmbed (outbound network OK), so the curated specimen
(`patterns/vqa-embeds.php`, page `/vqa-embeds/`) renders real provider output. The
CSS-0 inventory of the 18 specimens:

```txt
IFRAME (12)        ma.tt · wordpress.com · gutenbergtimes  (WordPress post embeds)
                   wordpress.org/plugins/activitypub · wordpress.org/themes/twentytwentyfive
                   wordpress.tv · videopress · youtube · ted   (video, 16:9 aspect)
                   spotify · soundcloud   (audio, provider-rounded iframe)
                   pinterest
BLOCKQUOTE + <script> (2)   bluesky (.bluesky-embed) · reddit (.reddit-embed-bq)
                            → the provider script swaps the quote for an iframe
                              client-side within ~1s
RAW/URL fallback (5)   mastodon · x/twitter · threads · instagram · example.com
                       → unresolved: the wrapper keeps the BARE URL as text
                         (not even an <a>), one line ~24px tall
LINK + <script> (1)    tumblr
```

**CSS-0 computed baseline** (content column ~645px):

```txt
youtube  figure 645x363, iframe responsive to 645 (core wp-embed-aspect-16-9 +
         wp-has-aspect-ratio give the ratio) — radius 0, overflow visible,
         margin 19.2px top / 0 bottom (browser default, uneven)
spotify  iframe 100% x 352, provider supplies its own border-radius:12px; figure 0
reddit   blockquote -> iframe 640 after script; bluesky -> iframe ~600 after script
mastodon BARE URL TEXT in the wrapper, 24px tall, unstyled, not a link
```

**Load-bearing takeaways**

1. **Width + aspect are already core's.** The figure is content-width (645) and the
   video iframes are responsive via core's `wp-embed-aspect-*` / `wp-has-aspect-ratio`.
   The theme must NOT re-impose width or aspect.
2. **The iframe interior is the provider's.** Spotify ships its own 12px radius;
   YouTube/TED ship their own chrome. The theme styles the OUTER figure/frame only.
3. **The real theme value is the FALLBACK.** An unresolved embed is a bare URL
   string — ugly and unlinked. A fallback card (surface + outline + the URL as a
   link) is the one place the theme adds genuine chrome.

---

## §2 — The three render buckets (and what the theme may touch)

```txt
A. IFRAME embeds (article / video / audio / pin)
   theme owns: figure vertical rhythm; media radius + overflow:clip; max-inline-size
   core owns:  width, responsive aspect (wp-embed-aspect-*), the iframe content
   never:      width/height/aspect override, anything inside the iframe

B. BLOCKQUOTE + provider <script> (bluesky, reddit, …)
   theme owns: the pre-script blockquote's OUTER spacing only (light touch)
   provider:   the <script> replaces the quote with its own iframe — leave it
   never:      restyle the provider blockquote internals or the upgraded iframe

C. RAW/URL fallback (unsupported / unreachable provider)
   theme owns: a fallback CARD — surface-container-low, outline-variant hairline,
               padding, the URL rendered as a body link, body-small note
   never:      treat the external-fetch failure as a VQA/test failure
```

---

## §3 — Selected route: theme owns the outer shell, nothing inside

- **Figure spacing**: tokenise the figure's vertical rhythm to `--space-md` (it is
  browser-default + uneven at CSS-0), consistent with the rest of post-content.
- **Media frame**: `border-radius: --md-sys-shape-corner-medium` + `overflow: clip`
  + `max-inline-size: 100%` on the embed media; **respect** core's aspect classes
  (do not set width/height/aspect-ratio ourselves).
- **Caption**: `figcaption.wp-element-caption` → body-small / on-surface-variant
  with a small top margin (matches the media-VQA caption contract).
- **Fallback card**: the RAW/URL bucket → a filled, outlined card with the URL as a
  link. This is theme-owned chrome, NOT a provider concern.
- **Alignment**: respect `alignwide` / `alignfull` / `aligncenter` (core layout).

**Rejected routes**

- ❌ Wrap every embed in a Card. Over-contract — provider iframes already carry
  their own surface/chrome; a theme card around a YouTube player is double framing.
- ❌ Force a uniform aspect-ratio. Core already owns aspect per provider; forcing it
  would crop/letterbox video and break audio/social heights.
- ❌ Style provider blockquote/script internals. Client-side script replaces them.
- ❌ Hard-fail the VQA when a fragile provider doesn't resolve (see §6).

---

## §4 — Token map (compose existing; no new tokens expected)

```txt
Figure / shell   margin-block --space-md ; (caption gap --space-xs/sm)
Media frame      border-radius --md-sys-shape-corner-medium ; overflow clip ;
                 max-inline-size 100%   (NO width / aspect — core owns those)
Caption          body-small ; color on-surface-variant ; margin-block-start --space-xs
Fallback card    background surface-container-low ; border 1px outline-variant ;
                 radius --comp-card-radius ; padding --space-md ;
                 link on-surface / underline ; note body-small on-surface-variant
```

---

## §5 — Contract principles (Do / Don't)

```txt
Do:
  figure vertical rhythm (tokenised)
  media radius + overflow:clip + max-inline-size:100%
  caption typography
  fallback / unresolved card
  respect core aspect classes + alignwide/alignfull
Don't:
  style inside the provider iframe / script / blockquote UI
  force width / height / aspect-ratio
  card-wrap every embed
  treat an external oEmbed failure as a test failure
```

---

## §6 — Fragile providers & fallback policy

`x/twitter`, `threads`, `instagram` (and often `mastodon`, `tumblr`) need
auth/script policies that fail under wp-env / server-side oEmbed; at CSS-0 they fall
to the RAW/URL bucket. They are kept in an explicit **"expected fallback"** section
of the specimen and are acceptance-tested ONLY for the fallback-card contract, never
for a live provider render. A distributable theme must degrade gracefully here.

---

## §7 — Write scope, fences, validation (DONE — blocks.css §17)

- **Write scope**: `assets/styles/blocks.css` `§17 core/embed` only. No `theme.json`,
  no provider JS, no baseline files.
- **Fences**: every selector under `.wp-block-post-content .wp-block-embed`
  (+ `__wrapper`). No bare `iframe {}` / `blockquote {}` rule; no `!important` into
  provider markup. `figcaption` is LEFT to prose.css (it already styles
  `.wp-block-post-content figcaption` = body-small / on-surface-variant / space-sm),
  so §17 adds no caption rule. The fallback surface is scoped with
  `:not(:has(iframe)):not(:has(blockquote))` so it lands ONLY on the unresolved
  raw-URL wrapper, never behind a resolved/loading provider media.
- **Validation (computed, both schemes)**:
  - figure `margin-block` = 16px (`--space-md`) ✓
  - wrapper `border-radius` = 12px (corner-medium) + `overflow: clip` ✓; iframe
    `display:block; max-inline-size:100%` ✓; video still responsive (core aspect
    intact, NO width/aspect override) ✓
  - iframe wrapper `background` = transparent ✓ (the `:has()` scope keeps the
    fallback surface OFF resolved embeds)
  - raw-URL fallback wrapper = `surface-container-low` + 1px `outline-variant` +
    16px padding, both schemes (dark 29,27,32 / light 247,242,250) ✓
- **Deferred**: a truly CLICKABLE link card (wrap the bare URL in `<a>`) is not
  possible in CSS — it needs a `render_block_core/embed` filter. §17 stops at the
  fallback SURFACE, as agreed.

---

## §8 — Explicitly NOT in this lane

- No styling INSIDE provider iframe / script / blockquote UI.
- No new tokens (composes existing `--space-*`, `--md-sys-shape-*`,
  `--md-sys-color-*`).
- No `render_block_core/embed` filter yet (the clickable fallback link card is
  deferred — §7).
- No `/embed/` template work — that is the SEPARATE adjacent lane below (§9).

---

## §9 — Adjacent lane (SEPARATE): the `/embed/` template — "Self / WP Embed Card"

There are **two distinct embed surfaces**; this doc (§1–§8) is only the first:

```txt
1. core/embed BLOCK   = external content placed INSIDE our post   → THIS doc
2. /embed/ TEMPLATE   = OUR post rendered as a card when embedded
                        on another (or our own) WordPress site     → this section
```

When someone pastes our post URL, their site fetches our **`/post-slug/embed/`**
endpoint (oEmbed discovery — no manual embed code needed between WordPress sites)
and drops it in an `<iframe>`. The layout of THAT card is owned by **our** theme's
embed template, customised through a different hook family than block CSS:

```txt
embed.php / embed template hierarchy   the /embed/ screen structure
enqueue_embed_scripts                  embed-only CSS inside the iframe
embed_head                             inline CSS if needed
embed_thumbnail_image_shape            featured image rectangular(top) vs floated
embed_thumbnail_image_size             embed-only image size
embed_content                          extra meta under the excerpt (e.g. date)
embed_content_meta                     footer action area
embed_site_title_html                  site name / logo
```

`add_theme_support('responsive-embeds')` may be ON, but the real contract there is
the `/embed/` template CSS — distinct from the core-embed wrapper we respect here.

**Why a separate lane**: this card belongs with the **ActivityPub object card /
attachment page** family (our content presented AS an object), not with the
external-embed-block shell. Plan: open a "Self / WP Embed Card" VQA — put a demo
post URL in an Embed block AND open `/demo-post/embed/` directly — observe the
WP-default embed card, then design an M3 Card/List contract for it.

Reference: WordPress Developer Blog,
[Customize WordPress embeds to match your theme](https://developer.wordpress.org/news/2025/02/customize-wordpress-embeds-to-match-your-theme/).
