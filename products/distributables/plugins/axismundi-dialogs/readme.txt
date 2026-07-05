=== Axismundi Dialogs ===
Contributors: kimjiwoon
Tags: sheet, drawer, dialog, offcanvas, block
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accessible Material Design 3 side and bottom sheets for Axismundi, composed from theme parts.

== Description ==

Axismundi Dialogs provides an `axismundi/dialogs` collection and `axismundi/sheet`
child blocks. The collection controls alignment, orientation, justification,
wrapping, and spacing. Each child is an editable trigger button that opens
a Material Design 3 side or bottom sheet built on the native `<dialog>` element.
The dialog supplies the top layer, scrim, focus containment, Escape-to-close, and
focus restoration; the plugin adds open/close, backdrop dismissal, scroll lock,
and a single-open-sheet policy.

The sheet content is a **Sheet template part**, so the theme owns the header,
close button, title, and body layout — the same `theme//slug` contract the core
Navigation overlay uses. An `axismundi/dialog-close` block lets a part place its
dismiss control anywhere.

The plugin registers the `sheet` template-part area; the Axismundi theme ships
default Navigation, Table of Contents, and Generic Sheet parts.

== Installation ==

1. Install and activate the Axismundi theme (recommended; the block works with
   any block theme, styled by its own fallbacks).
2. Upload and activate this plugin.
3. Insert the Sheets block, edit its trigger text, and choose each Sheet's
   template part, variant, and edge.

== Frequently Asked Questions ==

= Does this plugin require an external service? =

No. Everything runs locally in WordPress with the native `<dialog>` element and
the WordPress Interactivity API.

= Can it be used without the Axismundi theme? =

Yes. The sheet renders with its own token fallbacks, but it needs at least one
`sheet` area template part to show as content.

== Changelog ==

= 0.1.0 =

* Initial release: the `axismundi/dialogs` collection, `axismundi/sheet` host
  block (side / bottom, start / end edge, width, icon and editable trigger
  label), and the `axismundi/dialog-close` block.
* Native `<dialog>` modal with Interactivity API open/close, backdrop dismissal,
  document scroll lock, single-open-sheet policy, and focus restoration.
* Registers the `sheet` template-part area; Sheet content is a theme part.
* Reduced-motion and RTL support via logical properties.
