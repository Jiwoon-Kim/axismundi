# Axismundi VQA — embed blocks baseline

> Phase 1 reverse-direction baseline (CLAUDE.md #10): how WordPress core
> **embed-family** blocks (`core/embed`, oEmbed providers) render on the blank
> Axismundi theme before any embed-chrome contract. Harness: `patterns/vqa-embeds.php`
> (the curated provider set ported as-is from Omphalos), seeded as the `vqa-embeds`
> page. Captured 2026-06-07 on the workspace env (`localhost:8884`, theme
> `axismundi`). Dev-only, excluded from the ZIP.

## Headline finding

Editor block validation: **0 invalid blocks** — the ported embed markup
(type + providerNameSlug + matching is-type-* / is-provider-* classes) is the
editor's own save output, so it validates cleanly.

`core/embed` saves a static `<figure class="wp-block-embed …">` + bare URL; the
front-end autoembed (oEmbed) replaces the URL with provider HTML at render. The
DOM falls into three buckets:

1. **iframe** (youtube / ted / videopress / spotify / wordpress.tv / wp-embed
   cards / …): the theme owns only the outer frame.
2. **blockquote + provider `<script>`** (reddit / bluesky): the script upgrades
   client-side; the theme styles only the pre-script fallback.
3. **link fallback** (unsupported/unreachable URL): the theme owns a fallback card.

On this capture (page 21), of **17 embeds**: **16 resolved to iframe** (incl. the
WordPress-ecosystem wp-embed cards, youtube, ted, videopress, spotify, soundcloud,
bluesky, reddit, pinterest, and even the "fragile" x / tumblr), and **1 link
fallback** — `example.com/not-an-embeddable-resource` (intentional unsupported).

> Verification note: the **resolved DOM is the truth**, not a single static
> screenshot. External provider iframes are present in the DOM (counted) but render
> lazily/blank in a headless capture; the WP-ecosystem cards render visibly.
> Fragile providers (x/tumblr) may hydrate or fall back depending on oEmbed/script
> state — a link fallback is NOT a failure.

## Baseline (front render, page 21)

| Bucket | count | providers |
|---|---|---|
| iframe | 16 | wp-embed cards (matt-mullenweg, wordpress-com-news, gutenberg-times, plugin-directory, theme-directory) · video (wordpress-tv, videopress, youtube, ted) · social (bluesky, reddit) · audio (spotify, soundcloud) · pin (pinterest) · fragile (x, tumblr) |
| blockquote+script | (pre-hydration fallback for reddit/bluesky) |
| link fallback | 1 | example.com (unsupported) |

The theme later contracts only the **outer shell** (figure spacing, media radius,
caption, the link-fallback card) — never inside the provider iframe/script UI.

Screenshot of record: `tmp/baseline-embeds.png` (dev artifact, not committed).
Re-run: seed `patterns/vqa-embeds.php` → the `vqa-embeds` page and re-capture.
