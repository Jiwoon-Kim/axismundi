=== Axismundi ===

Contributors:      KIM JIWOON
Tags:              block-theme, full-site-editing, blog, microblog, two-columns, three-columns, accessibility-ready, custom-colors, custom-menu, threaded-comments, translation-ready
Requires at least: 6.5
Tested up to:      6.7
Requires PHP:      8.1
Stable tag:        1.0.0-rc1
License:           GPL-3.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-3.0.html

Material 3 design system block theme for WordPress, optimized for both long-form blogging and ActivityPub microblogging.

== Description ==

Axismundi is a token-driven block theme built on Material 3 design language. It targets two audiences in a single codebase:

* Long-form bloggers who want polished typography, accessible color, and full-site editing.
* Microbloggers using ActivityPub to federate short posts in an SNS-style feed.

Surfaces and layout primitives are split so each context reads correctly — long-form pages use a 65ch reading column with prose typography rhythm, while feed pages use a narrower 480–600px column with card-based posts.

= Highlights =

* **Material 3 token system** — every color, typography role, motion curve, and shape value resolves to a CSS custom property. No hardcoded hex.
* **Logical-property layout** — `padding-inline`, `margin-block`, `inline-size`, `block-size` throughout. RTL works out of the box.
* **Accessibility-ready** — WCAG 2.2 AA contrast on default palette, visible focus indicators, semantic markup, native keyboard navigation patterns.
* **Block style variants** — Buttons (filled / tonal / elevated / outlined / text), Group as Card (filled / elevated / outlined), Separator (5 styles), List (segmented).
* **Full-site editing** — theme.json defines color palette, typography, spacing, and layout sizes; templates use only core blocks plus registered styles.
* **Korean / CJK aware** — `word-break: keep-all` plus mixed-script line-height parity for clean Korean and English typesetting.
* **Dark scheme** — automatic via `prefers-color-scheme` plus explicit `data-theme="dark"` opt-in.

== Installation ==

1. In your WordPress admin, navigate to Appearance → Themes → Add New → Upload Theme.
2. Upload the Axismundi zip.
3. Click Activate.
4. (Optional) Customize via Appearance → Editor (Site Editor).

== Frequently Asked Questions ==

= Does Axismundi support page builders such as Elementor or Beaver Builder? =

Axismundi is a block theme. While page builders that hook into the standard the_content filter will render correctly inside posts and pages, the recommended editing surface is the WordPress Site Editor and Block Editor.

= Does Axismundi require any plugins? =

No. The base theme is self-contained. Two optional companion plugins extend it:

* **m3-blocks** — generic Material 3 components (chip, icon button, tabs, menu, etc.)
* **axismundi-blocks** — Axismundi-specific microblog composites (post card, composer, profile head)

Both are optional and ship separately.

= Is the theme RTL-compatible? =

Yes. All layout uses CSS logical properties; no `margin-left` / `padding-right` / etc. anywhere in the theme.

= Where is the source code? =

https://designbusan.ai.kr (placeholder until repository is published)

== Changelog ==

= 1.0.0-rc1 =

Initial release candidate. See docs/CHANGELOG.md for full history.

== Copyright ==

Axismundi WordPress theme, © 2026 KIM JIWOON.
Axismundi is distributed under the terms of the GNU General Public License, version 3 or later (GPL-3.0-or-later). The full text of the license is included in the LICENSE file in the theme root, and is also available at <https://www.gnu.org/licenses/gpl-3.0.html>.

This theme bundles only its own resources, all licensed GPL-3.0-or-later (code) or CC-BY-4.0 (documentation).
