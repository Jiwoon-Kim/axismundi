=== Axismundi Dialogs ===
Contributors: kimjiwoon
Tags: sheet, drawer, dialog, offcanvas, block
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accessible Material Design 3 side and bottom sheets for Axismundi, composed from theme parts.

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

The plugin registers `sheet` and `dialog` template-part areas. Axismundi Theme
0.1.6 or later ships editable Navigation, Table of Contents, and Generic Sheet
parts plus Basic, List, and Full-screen Dialog parts.

== Installation ==

1. Install and activate Axismundi Theme 0.1.6 or later (recommended; blocks work with
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

= 0.2.0 =

* Add a Post Quick View Trigger block: a microblog-style comments action (icon
  plus count) for a Post Template that opens the shared quick view, falling back
  to the post's comments anchor when the hub or scripting is unavailable.
* Add a singleton Post Quick View block: one per-page dialog that lazy-loads the
  clicked post over the REST API — title, meta, featured image, content, and a
  read-only comment thread with Reddit-style reply folding.
* Let logged-in readers post a top-level comment from a fixed composer without
  leaving the feed; the thread refetches on success and honours moderation.
  Anonymous and reply composing remain a link out to the full post.

= 0.1.0 =

* Initial release of the Dialogs collection with Sheet and Dialog host blocks.
* Side and bottom sheets with modal/standard, docked/detached, edge, width, and
  body/sheet scrolling controls.
* Basic, list, and full-screen Material 3 dialogs.
* Native `<dialog>` and Interactivity API runtime with animated dismissal,
  backdrop and Escape handling, focus restoration, responsive modal fallback,
  scroll lock, and smooth standard-sheet page push.
* Editable Sheet and Dialog template-part areas with title, icon, and close
  companion blocks.
* Reduced-motion and RTL support via logical properties.
