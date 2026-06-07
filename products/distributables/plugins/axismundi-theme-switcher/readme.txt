=== Axismundi Theme Switcher ===
Contributors: kimjiwoon
Tags: dark-mode, block, appearance, editor, color-scheme
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
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

= 0.1.0 =

* Initial companion block and color-scheme bridge.
