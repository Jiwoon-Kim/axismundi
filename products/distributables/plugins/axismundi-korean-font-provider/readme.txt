=== Axismundi Korean Font Provider ===
Contributors: kimjiwoon
Tags: fonts, korean, typography, font-library
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.3
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Optional Korean web-font provider for the Axismundi theme: Noto Sans KR and Noto Serif KR.

== Description ==

Axismundi Korean Font Provider supplies the `Noto Sans KR` and `Noto Serif KR`
web fonts and fills the Axismundi theme's regional CJK fallback slot when the
current document root has a Korean language tag (`ko` or `ko-*`).

Two roles:

* **Provider.** On activation the plugin enqueues `@font-face` rules (front end
  and block editor) for the two families. On a Korean document it sets the
  theme's `--axismundi-cjk-sans` / `--axismundi-cjk-serif` slots. The fonts are
  scoped to Hangul, so Latin keeps rendering in Roboto Flex / Roboto Serif.
* **Font Library collection.** The families are registered as the
  "Axismundi Korean Font Provider" collection, so they can be browsed,
  installed, and selected from Site Editor > Styles > Typography.

Without the plugin the Axismundi theme falls back to the operating system's CJK
font. Separate regional plugins can fill the same slot for Japanese or Chinese
documents without competing in a fixed font-family list. The current Korean
WOFF2 files are Hangul subsets; Korean Hanja continues to use the system fallback.

This plugin bundles no tracking and contacts no external service.

== Installation ==

1. Install and activate the Axismundi theme.
2. Upload and activate this plugin.
3. Korean text now renders in Noto Sans KR / Noto Serif KR. Optionally manage the
   families under Appearance > Editor > Styles > Typography.

== Frequently Asked Questions ==

= Does it require the Axismundi theme? =

Automatic fallback-slot integration requires Axismundi 0.1.3 or later. Other
themes can still select the registered Noto families explicitly from the Font
Library or reference their family names in CSS.

= Does it change Latin text? =

No. The `@font-face` rules are scoped to the Korean unicode range, so Latin and
numerals continue to use the theme's primary family.

= Which WordPress language setting selects the Korean fallback? =

The public document language (`<html lang>`), normally derived from Site
Language, selects it. A multilingual plugin may set that language per request.
The logged-in user's profile language translates wp-admin and does not select the
font used for published content.

= Does this package include Korean Hanja? =

No. The bundled WOFF2 files are deliberately limited to Hangul and Jamo. Shared
CJK ideographs fall through to the operating system font until a separate
regional Hanja subset is provided.

== Changelog ==

= 0.1.3 =

* Fill Axismundi's locale-aware CJK fallback slot only for Korean documents, so
  other regional font plugins can coexist without a fixed family-order conflict.
* Clarify that user-profile language controls admin UI translation, not the
  published document's regional glyph forms.

= 0.1.2 =

* Document the source URL, copyright, license, and license URL for each bundled
  Noto font family directly in readme.txt.

= 0.1.1 =

* Prepare the package for WordPress.org Plugin Check by removing the hidden
  distribution manifest and publishing the bundled-font notice as `NOTICE.txt`.

= 0.1.0 =

* Initial release: `@font-face` provider (front + editor) for Noto Sans KR and
  Noto Serif KR, plus a Font Library collection registration.

== Copyright ==

Axismundi Korean Font Provider, Copyright 2026 KIM JIWOON.
Plugin code is distributed under the GNU General Public License, version 3 or
later.

This plugin bundles the following third-party resources:

== Fonts ==

The original font files were converted and subset to WOFF2 for Korean web-font
delivery.

Noto Sans KR
Copyright 2014-2021 Adobe, with Reserved Font Name "Source".
License: SIL Open Font License, 1.1
License URI: https://openfontlicense.org/open-font-license-official-text/
Source: https://github.com/google/fonts/tree/main/ofl/notosanskr

Noto Serif KR
Copyright 2012 Google Inc.
License: SIL Open Font License, 1.1
License URI: https://openfontlicense.org/open-font-license-official-text/
Source: https://github.com/google/fonts/tree/main/ofl/notoserifkr

The verbatim license text and conversion provenance are preserved under each
family directory in `assets/fonts/`. See NOTICE.txt.
