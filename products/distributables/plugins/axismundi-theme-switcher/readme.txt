=== Axismundi Theme Switcher ===
Contributors: kimjiwoon
Tags: dark-mode, block, appearance, editor, color-scheme
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.8
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A light, dark, and auto color-scheme switcher block that remembers what the reader picked.

== Description ==

The `axismundi/theme-switcher` block puts an Auto / Light / Dark control on the
page, as a connected button group or a single cycling button, and writes the
choice to `html[data-theme]`. The choice is kept in a first-party cookie and
reapplied before the next page paints, so returning to the site does not flash
the wrong scheme.

The block reads its colors, corner sizes and motion from the Material Design
system custom properties, so it looks as intended under any theme that publishes
them, and falls back to Material's own baseline where a theme publishes none.
The Axismundi theme supports the `html[data-theme]` contract natively.

== Installation ==

1. Install and activate the plugin.
2. Insert the Theme Switcher block wherever a light / dark / auto control is
   needed -- a header template part, a footer, or a page.
3. For the switch to repaint the whole site, use a theme or stylesheet that
   responds to `html[data-theme]`. The Axismundi theme does so natively.

== Frequently Asked Questions ==

= Does this plugin require an external service? =

No. The switcher runs locally in WordPress and stores the selected mode in a
first-party cookie named `axismundi_theme`.

= Can this plugin be used without the Axismundi theme? =

Yes. The block reads its colors, corner sizes and motion from the Material
Design system custom properties -- `--md-sys-color-*` and the shape and motion
scales beside it -- so it looks as intended under any theme that publishes them.
Under a theme that publishes none it falls back to Material's own baseline
colors, and it still renders and still toggles the `data-theme` attribute; the
site around it changes only if something consumes `html[data-theme]`.

== Changelog ==

= 0.1.8 =
* Stop inserting a switcher into the theme header automatically. The block now
  goes only where it is placed, and a theme that wants one in its header can put
  it in the header template. Headers that already show one are unaffected: the
  Axismundi theme places it itself.

Earlier releases are listed in changelog.txt.

