=== Axismundi Fonts: Noto CJK Korean ===
Contributors: kimjiwoon
Tags: fonts, korean, noto, typography, font-library
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.1
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Optional Korean web-font provider for the Axismundi theme: Noto Sans KR and Noto Serif KR.

== Description ==

Axismundi Fonts: Noto CJK Korean supplies the `Noto Sans KR` and `Noto Serif KR`
web fonts under the exact family names the Axismundi theme already references in
its font stacks (for example `"Roboto Flex", "Noto Sans KR", system-ui`).

Two roles:

* **Provider.** On activation the plugin enqueues `@font-face` rules (front end
  and block editor) for the two families. The Korean fallback in the theme's
  stacks then resolves to Noto. The fonts are scoped to the Korean unicode range,
  so Latin text keeps rendering in Roboto Flex / Roboto Serif.
* **Font Library collection.** The families are registered as the
  "Axismundi Fonts: Noto CJK Korean" collection, so they can be browsed,
  installed, and selected from Site Editor > Styles > Typography.

Without the plugin the Axismundi theme falls back to the operating system's
Korean font; the theme keeps working. With the plugin active, Korean renders in
Noto. The same pattern extends to other languages (Japanese / Chinese) as
separate companion plugins.

This plugin bundles no tracking and contacts no external service.

== Installation ==

1. Install and activate the Axismundi theme.
2. Upload and activate this plugin.
3. Korean text now renders in Noto Sans KR / Noto Serif KR. Optionally manage the
   families under Appearance > Editor > Styles > Typography.

== Frequently Asked Questions ==

= Does it require the Axismundi theme? =

It is built for Axismundi, but it works with any theme whose font stacks
reference the `Noto Sans KR` or `Noto Serif KR` family names.

= Does it change Latin text? =

No. The `@font-face` rules are scoped to the Korean unicode range, so Latin and
numerals continue to use the theme's primary family.

== Changelog ==

= 0.1.1 =

* Prepare the package for WordPress.org Plugin Check by removing the hidden
  distribution manifest and publishing the bundled-font notice as `NOTICE.txt`.

= 0.1.0 =

* Initial release: `@font-face` provider (front + editor) for Noto Sans KR and
  Noto Serif KR, plus a Font Library collection registration.

== Copyright ==

Axismundi Fonts: Noto CJK Korean, Copyright 2026 KIM JIWOON.
Plugin code is distributed under the GNU General Public License, version 3 or
later.

Bundled fonts (Noto Sans KR, Noto Serif KR) are Copyright The Noto Project
Authors, licensed under the SIL Open Font License 1.1. The license text and
sources are preserved under `assets/fonts/*/`. See NOTICE.txt.
