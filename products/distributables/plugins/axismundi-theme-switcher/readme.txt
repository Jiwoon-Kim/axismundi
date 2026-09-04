=== Axismundi Theme Switcher ===
Contributors: kimjiwoon
Tags: dark-mode, block, appearance, editor, color-scheme
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.7
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

Yes. The block reads its colors, corner sizes and motion from the Material
Design system custom properties -- `--md-sys-color-*` and the shape and motion
scales beside it -- so it looks as intended under any theme that publishes them.
Under a theme that publishes none it falls back to Material's own baseline
colors, and it still renders and still toggles the `data-theme` attribute; the
site around it changes only if something consumes `html[data-theme]`.

== Changelog ==

= 0.1.7 =
* Add a Cycle button visibility setting -- off, mobile or always -- so the
  switcher can show its three-button group on wide screens and compress to a
  single cycling button on narrow ones. It replaces the block style that used to
  make that choice; existing content keeps rendering as it did.
* On mobile, switch at the breakpoint the active theme declares, or WordPress's
  own default where a theme declares none.
* Require WordPress 7.1, which is where a theme can declare that breakpoint.
* Add a Size setting with the five Material Design sizes, from extra small to
  extra large.
* Add a Show labels setting for the button group, so its three modes can show
  icons alone. The names stay available to screen readers either way.
* Add a Show tooltips setting. A button with no visible name shows one on hover,
  on keyboard focus, or on a press and hold on a touchscreen.
* Add a Standard icon button setting for the cycling button, which drops its
  container so the symbol alone carries the color scheme.
* Add alignment and justification, so the control can sit wide, full width, or
  to one side of the space it is given.
* Rebuild the button group as Material Design's connected group: separate
  segments with rounded ends, an inner corner that shrinks while pressed, and a
  fully rounded segment for the mode in use.
* Add Filled, Tonal and Outlined block styles, which color the mode in use
  differently from the rest.
* Take colors, corner sizes and motion from the Material Design system
  properties directly, so the block looks right under any theme that publishes
  them rather than only under one palette. The border radius control is gone
  from the sidebar with them: the corners now follow the theme's shape scale.
* Show the cycling button as unselected while the color scheme is Auto, and as
  selected once a reader chooses Light or Dark. A filled symbol now means
  selected on both surfaces, and a group segment fills its symbol on hover.
* Gather the settings into a Display panel with an options menu and Reset all,
  so a changed setting can be put back without remembering what it was.
* Send the switcher already carrying its icon, its name and the current scheme,
  instead of leaving them blank until scripts run.
* Fix several switchers on one screen disagreeing in the editor, and the block
  appearing empty in the editor's mobile preview.
* Version the block's editor script by its own file, so an updated editor is
  never served from a stale cache.

Earlier releases are listed in changelog.txt.

