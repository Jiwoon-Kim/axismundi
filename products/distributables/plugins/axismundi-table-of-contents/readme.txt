=== Axismundi Table of Contents ===
Contributors: kimjiwoon
Tags: table of contents, toc, headings, navigation, block
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

On-page table of contents block that builds from a post's headings and keeps the heading ids in sync.

== Description ==

Axismundi Table of Contents provides the `axismundi/toc` block: a dynamic,
server-rendered list built from the current post's headings, with a scroll-spy
that marks the section you are reading.

The block render and a `render_block_core/post-content` filter share one
deterministic slug function, so the table-of-contents anchors always match the
heading ids — regardless of render order or where you place the block (a template
aside or inside the content). Author-provided HTML anchors are respected, and
duplicate headings are de-duplicated (`-2`, `-3`).

* Builds from `h2`–`h4` by default (configurable per block).
* Unordered list by default; switch to a numbered (ordered) list per block.
* Emits the shared `toc-list` / `toc-h2…h4` / `is-current` class vocabulary so a
  theme's table-of-contents skin couples for free; ships a Material Design 3
  token skin (with fallbacks) in the meantime.
* Scroll-spy marks the current heading and clears when you leave the content
  (above the first heading or past the post content).

== Installation ==

1. Install and activate the Axismundi theme (for the Material Design 3 skin; the
   block also works under any theme that styles `.toc-list` / `.is-current`).
2. Upload and activate this plugin.
3. Add the Table of Contents block where you want it — typically a sidebar aside
   in the single-post template, or inside a Details block above the content.

== Frequently Asked Questions ==

= Does this plugin require an external service? =

No. The table of contents is built on the server from the post's own headings.

= How are the heading ids created? =

From the heading text via `sanitize_title()`, de-duplicated in document order. If
a heading already has an HTML anchor, that id is kept. The same function feeds the
block and the heading-id injection, so the anchors always resolve.

= Does it work with non-Latin headings? =

Yes. Non-ASCII headings (for example Korean) are percent-encoded into the same
value on both the heading id and the table-of-contents link, and the scroll-spy
matches them literally.

== Changelog ==

= 0.1.0 =

* Initial release: the `axismundi/toc` block (server-rendered list from post
  headings) with deterministic, shared heading-id slugs and `is-current`
  scroll-spy.
* IntersectionObserver plus an rAF scroll fallback decide the active section from
  one rule; the active state clears when the reading position leaves the content.
* Material Design 3 token skin (with fallbacks) over the shared `toc-list` /
  `is-current` vocabulary; smooth in-page scrolling is owned by the theme.
