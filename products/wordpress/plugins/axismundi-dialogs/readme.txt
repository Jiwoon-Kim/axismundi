=== Axismundi Dialogs ===
Contributors: kimjiwoon
Tags: sheet, drawer, dialog, offcanvas, block
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.3.0
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

A **Dialog Button Group** holds **Dialog Button** and **Dialog Icon Button**
blocks: a Material 3 standard button group with size, shape, distribution and
selection on the group, and an icon, a toggle state and a disabled state on each
button. The buttons render as WordPress buttons, so a theme's button styles
apply to them, and they convert to and from `core/button`. Each button's
**Action** says what it does - a command, a form submit or reset, or opening a
Navigation Overlay template part - and the ARIA follows from that choice rather
than being authored by hand.

A **Dialog Icon** block draws an icon from the theme's icon font or the
WordPress Icon Registry; the same renderer paints the icons inside the buttons.

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

= 0.3.0 =

* Adds three blocks that build a Material 3 button group: **Dialog Button
  Group**, **Dialog Button** and **Dialog Icon Button**. The group owns the
  shape of the set - size, shape, distribution, and whether its buttons behave
  as a selection - and each button carries what only it can know.
* The buttons render as `.wp-block-button` around `.wp-block-button__link
  .wp-element-button`, so a theme that styles WordPress buttons styles these,
  and the theme's button style variations (Tonal, Outlined, Text, Elevated)
  appear on them.
* Size (Extra small to Extra large) and Shape (Round or Square) come from the
  Material 3 button tables, each with its own corner morph on press.
* Togglable buttons: a group can require a selection, allow one or several, and
  a selected button reports `aria-pressed`. A group of links opts out instead of
  being silently rewritten into buttons.
* Disabled is available on every button, with the Material 3 disabled treatment
  for each colour style.
* Distribution spreads a group's buttons across the available width, widening
  the selected one as the specification does.
* Dialog Button can show an icon beside its label, and either button can swap
  to a different icon while it is selected and fill the icon font's `FILL`
  axis. Both icon sources are supported - the theme's icon font and the
  WordPress Icon Registry.
* Dialog Icon Button shows a Material 3 plain tooltip on hover and focus,
  carrying the accessible name it already has. The tooltip is a popover, so it
  paints above a dialog, and it is shown in the editor as well.
* Buttons convert between the three blocks, and to and from `core/button`,
  keeping everything both sides understand.
* **Action** says what a button is for, and the markup follows from it: a plain
  command, a form submit or reset, opening the **Overlay template**, or closing
  a navigation overlay. Advanced shows the element the choice produces,
  read-only.
* Opening the Overlay template renders the chosen `navigation-overlay` template
  part the way core's Navigation overlay does - a `div` with `role="dialog"`,
  `html.has-modal-open`, a focus trap, Escape, and close on leaving the page -
  so the two behave alike on one page. The plugin's own dialogs stay native
  `<dialog>` elements.
* Dialog Icon is now a shared icon primitive aligned with `core/icon`: one
  renderer for both sources, in the editor and on the page. A preset Font size
  now reaches the page, flip and rotation apply to icon-font glyphs, and a Label
  makes either source `role="img"` while an unlabelled icon is hidden from
  assistive technology.

Earlier releases are listed in changelog.txt.
