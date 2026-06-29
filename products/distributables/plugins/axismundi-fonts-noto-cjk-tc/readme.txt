=== Axismundi Fonts: Noto CJK Traditional Chinese ===
Contributors: kimjiwoon
Tags: fonts, chinese, noto, typography, font-library
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Optional Traditional Chinese web-font provider for the Axismundi theme: Noto Sans TC.

== Description ==

Axismundi Fonts: Noto CJK Traditional Chinese supplies the `Noto Sans TC` web
font and fills the Axismundi theme's sans-serif regional CJK fallback slot for
Traditional Chinese content. It matches the script subtag `zh-Hant`
first, and also the legacy region tags `zh-TW`, `zh-HK`, and `zh-MO`, on the
document root or an inline subtree.

Two roles:

* **Provider.** On activation the plugin enqueues `@font-face` rules (front end
  and block editor) for the family. For Traditional Chinese content it sets
  the theme's `--axismundi-cjk-sans` slot. Latin keeps
  rendering in Roboto Flex / Roboto Serif because the theme lists Roboto ahead of
  the slot.
* **Font Library collection.** The family is registered as the
  "Axismundi Fonts: Noto CJK Traditional Chinese" collection, so they can be
  browsed, installed, and selected from Site Editor > Styles > Typography.

Because the slot is keyed on the Traditional Chinese language tags, this plugin
coexists with the Korean, Japanese (and future Simplified Chinese) regional
packages without competing in a fixed font-family list: each language run
resolves to its own regional font, so shared CJK ideographs render in Traditional
glyph forms on Traditional Chinese text. Without any regional plugin the Axismundi
theme falls back to the operating system's CJK font.

This plugin bundles no tracking and contacts no external service.

== Installation ==

1. Install and activate the Axismundi theme.
2. Upload and activate this plugin.
3. Traditional Chinese sans-serif text now renders in Noto Sans TC.
   Optionally manage the family under Appearance > Editor > Styles > Typography.

== Frequently Asked Questions ==

= Does it require the Axismundi theme? =

Automatic fallback-slot integration requires Axismundi 0.1.3 or later. Other
themes can still select the registered Noto family explicitly from the Font
Library or reference its family name in CSS.

= Does it change Latin text? =

No. The `@font-face` rules are scoped to the Chinese and CJK unicode ranges, so
Latin and numerals continue to use the theme's primary family.

= Which language tags select the Traditional Chinese fallback? =

The public document language (`<html lang>`): `zh-Hant` (and `zh-Hant-*`), plus
the legacy region tags `zh-TW`, `zh-HK`, and `zh-MO`. A bare `zh` is left to the
operating-system font, since it does not state Traditional vs Simplified. The
logged-in user's profile language translates wp-admin and does not select the
font used for published content.

= Does this package include Extension A Hanzi? =

Yes. The bundled WOFF2 file includes the shared CJK ideographs — CJK Unified
Ideographs, Extension A, and Compatibility Ideographs — plus Bopomofo, so
Traditional Chinese (including many personal and place names) renders in
Traditional glyph forms. The files are correspondingly large.

== Changelog ==

= 0.1.0 =

* Initial release: `@font-face` provider (front + editor) for Noto Sans TC,
  filling Axismundi's locale-aware sans-serif CJK fallback slot for
  Traditional Chinese language tags, plus a Font Library collection registration.

== Copyright ==

Axismundi Fonts: Noto CJK Traditional Chinese, Copyright 2026 KIM JIWOON.
Plugin code is distributed under the GNU General Public License, version 3 or
later.

This plugin bundles the following third-party resources:

== Fonts ==

The original font files were converted and subset to WOFF2 for Traditional
Chinese web-font delivery.

Noto Sans TC
Copyright 2014-2021 Adobe, with Reserved Font Name "Source".
License: SIL Open Font License, 1.1
License URI: https://openfontlicense.org/open-font-license-official-text/
Source: https://github.com/google/fonts/tree/main/ofl/notosanstc

The verbatim license text and conversion provenance are preserved under each
family directory in `assets/fonts/`. See NOTICE.txt.
