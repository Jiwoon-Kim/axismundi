=== Axismundi Dialogs ===
Contributors: kimjiwoon
Tags: sheet, drawer, dialog, offcanvas, block
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.2.5
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accessible Material Design 3 sheets and dialogs for Axismundi, composed from theme template parts.

== Description ==

Axismundi Dialogs provides an `axismundi/dialogs` collection with Sheet and Dialog
child blocks. The collection controls alignment, orientation, justification,
wrapping, and spacing. Each child has an editable open button and renders a
native `<dialog>` host.

Sheets support side and bottom geometry, modal or standard presentation,
docked or detached modal side sheets, start/end edges, and body-only or whole-
sheet scrolling. Standard side sheets resize the site on larger screens and
fall back to a modal presentation on compact screens. Dialogs support basic,
list, and full-screen layouts.

The native dialog supplies the top layer, scrim, focus containment, and focus
restoration. The plugin adds animated open/close, Escape and backdrop dismissal,
modal scroll locking, responsive presentation, smooth standard-sheet page push,
and a single-open-dialog policy.

The sheet content is a **Sheet template part**, so the theme owns the header,
close button, title, and body layout — the same `theme//slug` contract the core
Navigation overlay uses. An `axismundi/dialog-close` block lets a part place its
dismiss control anywhere.

WordPress core reserves the template-part area vocabulary. The plugin therefore
does not register custom `sheet` or `dialog` areas: Axismundi Theme 0.1.12 or
later supplies the default parts in the supported **Uncategorized** area, where
they remain editable in the Site Editor. The picker distinguishes them by their
`sheet-` and `dialog-` slugs. This preserves the standard `theme//slug`
template-part contract while keeping behavior with the plugin.

== Installation ==

1. Install and activate Axismundi Theme 0.1.10 or later (recommended; blocks work with
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
template part to show as content. With Axismundi Theme, use a Sheet or Dialog
part from the Site Editor's Uncategorized area.

== Changelog ==

= 0.2.5 =
* Dialog Icon can now take its icon from the theme's icon font (a Material
  Symbols name such as `info`) or from the WordPress Icon Registry (a
  registered icon such as `core/info`). The block stores which one it means.
  Existing Dialog Icon blocks keep rendering exactly as before.
* Icon Registry icons: choose from an Icon library in the toolbar, flip or
  rotate them, and set their width, colour, border and padding.
* Icon font icons: choose the icon font, set the font size, and adjust the
  Fill, Weight, Grade and Optical size axes, each resettable on its own.
* Dialog Icon can be used in posts and pages, not only inside a Dialog
  template part.
* The Sheet and Dialog open button now takes its size, shape, colour and
  states from the theme's button styles (`wp-element-button`) instead of
  drawing its own filled Material 3 button. On a theme that styles WordPress
  buttons it matches that theme's buttons; on one that does not, it is a plain
  button.
* The close button's hover transition uses the Material 3 Expressive motion
  curve.
* Requires WordPress 7.1, which provides the Icon Registry.
* The editor script is now built from `src/`; the readable source ships with
  the plugin.

Earlier releases are listed in changelog.txt.
