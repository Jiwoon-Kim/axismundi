=== Axismundi Emoji ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Custom and Unicode emoji: custom emoji with review and caching, a self-hosted Noto Color Emoji fallback, and one editor picker.

== Description ==

Axismundi Emoji handles both kinds of emoji a WordPress site meets.

= Custom emoji =

Custom emoji are site-specific images addressed by a `:shortcode:` inside otherwise plain text. They work on a site of their own, and federate as FEP-9098 declarations when a federation plugin is present.

* Local: upload emoji through WordPress's own upload validation. A `:shortcode:` for one of them renders as its image in posts and comments, and is declared in the outbound `tag` array of the Notes, Articles, and Actors that use it. Emoji marked local-only render at home and travel as their shortcode, never as an image.
* Received: remote emoji declared by other servers are recorded, held in an authority-gated review queue, verified against their own authority, cached as bytes once approved, and substituted into received Object bodies, Actor names, and Actor biographies. A shortcode nobody approved stays text, and a remote emoji is never matched by name alone.
* Emoji reactions (FEP-c0e0) belong to Axismundi Activities; this plugin observes their declared custom emoji so approved reaction chips render through the same cache.

= Unicode emoji =

Unicode emoji stay text. Where a browser cannot draw one — flags on Windows, the newest Emoji 17 sequences on older systems — this plugin can draw it with a bundled Noto Color Emoji font instead of replacing it with an image. The emoji remains the same character for copying, searching, and screen readers.

Choose the behaviour under Emojis › Settings (see below). WordPress's own image fallback stays available as the last resort, and can be turned off.

= Emoji picker =

One toolbar button in the block editor opens a picker with recent emoji, this site's custom emoji, and the Unicode 17.0 RGI set grouped by category. Choosing a custom emoji inserts its `:shortcode:`; choosing a Unicode emoji inserts the character itself. Nothing but text is saved.

The design is recorded in `docs/AXISMUNDI-EMOJI-ARCHITECTURE.md` and `docs/AXISMUNDI-EMOJI-UNICODE.md` at the repository root.

== Settings ==

Emojis › Settings (requires the `manage_options` capability).

Rendering:

* Automatic fallback (default) — the browser draws emoji itself where it can. Where it cannot draw a flag, the flag is drawn with a small flags-only font (about 700 KB), downloaded only by browsers that need it.
* Use Noto Color Emoji — every Unicode emoji on the site is drawn with the complete Noto Color Emoji font (about 1.9 MB), so emoji look the same on every device. The font is downloaded once, on the first page with an emoji, and cached.
* WordPress fallback only — the plugin leaves Unicode emoji alone and loads no font.

WordPress emoji images: a switch for WordPress's own fallback, which replaces emoji a browser cannot draw with images from WordPress.org. Turn it off to make no emoji image requests to WordPress.org from the site's pages. Feeds and emails are not affected; WordPress handles emoji there on its own.

== External services ==

The plugin's own Unicode fallback makes no external requests: the fonts and the emoji catalogue are served from this site.

* Custom emoji received from other servers: when a federated post, profile, or reaction declares a custom emoji, the plugin fetches that emoji's metadata and image from the declaring server (the URLs in its ActivityPub `Emoji` declaration) to verify and cache it. This happens only for content that reached this site through federation, and remote images are cached locally rather than hotlinked. The terms and privacy policy that apply are those of each remote server.
* WordPress emoji images: WordPress core, not this plugin, loads emoji images from `s.w.org` (WordPress.org) where a browser cannot draw an emoji. The Settings switch above turns this off on the site's pages. WordPress.org privacy policy: https://wordpress.org/about/privacy/

== Bundled fonts and data ==

* Noto Color Emoji, COLRv1, googlefonts/noto-emoji v2.051 (commit 8998f5dd683424a73e2314a8c1f1e359c19e8742), converted to WOFF2 without glyph changes, and a flags-only subset of it. SIL Open Font License 1.1. License, provenance, and SHA-256 checksums are in `assets/fonts/noto-color-emoji/` (`OFL.txt`, `source.txt`, `manifest.json`). The fonts are also offered in the Font Library as an "Axismundi Emoji" collection.
* Unicode Emoji 17.0 RGI catalogue, derived from Unicode's `emoji-test.txt`. Unicode License; see `assets/unicode/catalogue/rgi-17.0.LICENSE.txt`.

== Changelog ==

= 0.3.0 =
* Unicode emoji rendering: a self-hosted Noto Color Emoji font fallback that keeps emoji as text, with three modes under Emojis › Settings — Automatic fallback (flags subset only where the browser needs it), Use Noto Color Emoji (the complete font for every emoji), and WordPress fallback only.
* A switch for WordPress's own emoji image fallback, so a site can make no emoji image requests to WordPress.org.
* Bundles Noto Color Emoji COLRv1 v2.051 as WOFF2 (complete font and RGI flags subset), also offered as a Font Library collection.
* The Unicode 17.0 RGI emoji catalogue moved here from Axismundi Activities; its REST route is now `axismundi/v1/emoji/unicode`.
* The editor picker adds Unicode emoji and follows the reaction picker's layout: search, a category jump strip, and one scrolling page of collapsible sections.
* Custom emoji now render in ordinary posts and comments, not only in federated Objects and Actor profiles. Only this site's own emoji are matched.
* A post's outbound emoji declaration no longer goes missing when its content has already been rendered with emoji images.

Earlier releases are listed in changelog.txt.

== Bundled emoji ==

The plugin ships one emoji of its own, `:axismundi:` at `emoji/axismundi.webp` — a
200x200 WebP derived from the project's brand symbol, 5.6 KB, transparency intact.
Its project-owned brand-asset provenance and GPL-3.0-or-later release are recorded beside
the file in `emoji/LICENSE.txt`.

One size, because FEP-9098 gives an Emoji exactly one `icon.url`: there is no srcset
in the emoji contract, so a second file would federate nothing. 200px is generous
enough for a picker tile and scales down cleanly to the ~1.2em inline case.

It also serves as the outbound conformance fixture, so the test suite never depends
on another instance's restricted asset.
