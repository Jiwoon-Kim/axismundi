=== Axismundi Theme Switcher ===
Contributors: kimjiwoon
Tags: dark-mode, block, appearance, editor, color-scheme
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.5
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Companion block and color-scheme bridge for the Axismundi light / dark / auto theme switcher.

== Description ==

Axismundi Theme Switcher provides the `axismundi/theme-switcher` block and the
early color-scheme bridge that applies the persisted `axismundi_theme` cookie
before paint. The Axismundi theme owns the `data-theme` token selectors; this
plugin owns the UI, persistence, and editor preview sync.

== Installation ==

1. Install and activate the Axismundi theme.
2. Upload and activate this plugin.
3. Insert the Theme Switcher block where a light / dark / auto control is
   needed.

== Frequently Asked Questions ==

= Does this plugin require an external service? =

No. The switcher runs locally in WordPress and stores the selected mode in a
first-party cookie named `axismundi_theme`.

= Can this plugin be used without the Axismundi theme? =

The block still renders and toggles the `data-theme` attribute, but the visual
color-scheme change depends on a theme or stylesheet that consumes
`html[data-theme]` selectors.

== Changelog ==

= 0.1.5 =

* Prepare the package for WordPress.org Plugin Check by removing the hidden
  distribution manifest and relying on WordPress.org translation loading.
* Prefix render-template variables with the full plugin prefix.

= 0.1.4 =

* The front-end switcher now dispatches the `axismundi-theme-scheme-change` event
  (matching the editor preview), so JS consumers that cache theme colours (canvas,
  charts, themed visuals) can re-read tokens on a light / dark / auto change.
  `data-theme` plus this event are the public client-side contract.

= 0.1.3 =

* Stop applying the persisted theme scheme to the top-level WordPress admin
  document. Editor preview and Style Book scheme synchronization now stays
  scoped to preview documents, avoiding admin chrome color-scheme leakage.

= 0.1.2 =

* Animate the Material Symbols FILL axis on interaction: the cycle icon fills
  on hover / focus / press, and the selected segment stays filled. The axis
  interpolates continuously (the Axismundi theme registers `--md-icon-fill` via
  `@property`); reduced-motion users get the instant state.

= 0.1.1 =

* Harden the cycle / segmented icons: notranslate, translate="no", and
  draggable="false" on the Material Symbols spans so machine translation
  cannot rewrite the ligature text and the glyph cannot be dragged out.
* The icon-box contract (1em box, overflow clip) is now inherited from the
  Axismundi theme's icons.css — fixes a header overflow while the icon font
  was still loading.

= 0.1.0 =

* Initial release: the `axismundi/theme-switcher` block (light / dark / auto)
  and the early color-scheme bridge that applies the persisted cookie before
  paint.
* Block Hooks auto-insertion of the cycle control into the theme header.
* Material Design 3 styling that consumes the Axismundi theme tokens (with M3
  fallbacks): a standard icon button for the cycle style and a segmented
  control for the default three-mode layout.
