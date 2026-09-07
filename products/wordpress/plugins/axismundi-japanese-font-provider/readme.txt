=== Axismundi Japanese Font Provider ===
Contributors: kimjiwoon
Tags: fonts, japanese, typography, font-library
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Optional Japanese web-font provider for the Axismundi theme: Noto Sans JP and Noto Serif JP.

== Description ==

Axismundi Japanese Font Provider supplies the `Noto Sans JP` and `Noto Serif
JP` web fonts and fills the Axismundi theme's regional CJK fallback slot for
Japanese-language content (`lang="ja"`, on the document root or an inline
subtree).

Two roles:

* **Provider.** On activation the plugin enqueues `@font-face` rules (front end
  and block editor) for the two families. For Japanese-tagged content it sets the
  theme's `--axismundi-cjk-sans` / `--axismundi-cjk-serif` slots. Latin keeps
  rendering in Roboto Flex / Roboto Serif because the theme lists Roboto ahead of
  the slot.
* **Font Library collection.** The families are registered as the
  "Axismundi Japanese Font Provider" collection, so they can be browsed,
  installed, and selected from Site Editor > Styles > Typography.

Because the slot is keyed on `:lang(ja)`, this plugin coexists with the Korean
(and future Chinese) regional packages without competing in a fixed font-family
list: each language run resolves to its own regional font, so shared CJK
ideographs render in Japanese glyph forms on Japanese text. Without any regional
plugin the Axismundi theme falls back to the operating system's CJK font.

This plugin bundles no tracking and contacts no external service.

== Installation ==

1. Install and activate the Axismundi theme.
2. Upload and activate this plugin.
3. Japanese text now renders in Noto Sans JP / Noto Serif JP. Optionally manage
   the families under Appearance > Editor > Styles > Typography.

== Frequently Asked Questions ==

= Does it require the Axismundi theme? =

Automatic fallback-slot integration requires Axismundi 0.1.3 or later. Other
themes can still select the registered Noto families explicitly from the Font
Library or reference their family names in CSS.

= Does it change Latin text? =

No. The `@font-face` rules are scoped to the Japanese and CJK unicode ranges, so
Latin and numerals continue to use the theme's primary family.

= Which language setting selects the Japanese fallback? =

The public document language (`<html lang>`), normally derived from Site
Language, selects it; an inline `lang="ja"` subtree selects it for that run only.
A multilingual plugin may set the document language per request. The logged-in
user's profile language translates wp-admin and does not select the font used for
published content.

= Does this package include Kanji? =

Yes. Unlike the Korean package, the bundled WOFF2 files include the shared CJK
ideographs (CJK Unified Ideographs, Extension A, and Compatibility Ideographs)
alongside Hiragana and Katakana, so Japanese Kanji renders in Japanese glyph
forms. The files are correspondingly larger than a Kana-only subset.

== Changelog ==

= 0.1.0 =

* Initial release: `@font-face` provider (front + editor) for Noto Sans JP and
  Noto Serif JP, filling Axismundi's locale-aware CJK fallback slot for
  `:lang(ja)`, plus a Font Library collection registration.

== Copyright ==

Axismundi Japanese Font Provider, Copyright 2026 KIM JIWOON.
Plugin code is distributed under the GNU General Public License, version 3 or
later.

This plugin bundles the following third-party resources:

== Fonts ==

The original font files were converted and subset to WOFF2 for Japanese web-font
delivery.

Noto Sans JP
Copyright 2014-2021 Adobe, with Reserved Font Name "Source".
License: SIL Open Font License, 1.1
License URI: https://openfontlicense.org/open-font-license-official-text/
Source: https://github.com/google/fonts/tree/main/ofl/notosansjp

Noto Serif JP
Copyright 2012 Google Inc.
License: SIL Open Font License, 1.1
License URI: https://openfontlicense.org/open-font-license-official-text/
Source: https://github.com/google/fonts/tree/main/ofl/notoserifjp

The verbatim license text and conversion provenance are preserved under each
family directory in `assets/fonts/`. See NOTICE.txt.
