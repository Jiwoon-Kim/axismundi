=== Axismundi Dialogs ===
Contributors: kimjiwoon
Tags: dialog, bottom sheet, side sheet, button group, material design
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Material Design 3 dialogs, bottom sheets and side sheets kept as template parts, opened by accessible button groups.

== Description ==

Axismundi Dialogs builds Material Design 3 dialogs and sheets, and the buttons
that open them, from native HTML and WordPress blocks.

= Dialog surfaces =

A dialog or sheet is not a block placed in a post. It is a template part in the
**Dialog Surface** area, managed in the Site Editor the way core manages a
Navigation Overlay: a button in a post or template names the part, and the part
is rendered once, at the end of the page.

The part's root is the **Dialog** block, a native `<dialog>` element. Its
presentation is a choice of four:

* **Dialog - Basic** - a centred dialog.
* **Dialog - Full screen** - fills the window on compact screens and becomes a
  basic dialog from 600px up.
* **Sheets - Bottom** - a bottom sheet with an optional drag handle. The handle
  moves the sheet between its initial and expanded heights by click, keyboard,
  drag or fling.
* **Sheets - Side** - a side sheet on the start or end edge.

Sheets can be modal or standard. A standard side sheet stays open beside the
page and either resizes or moves the page to make room; on compact screens it
opens as a modal. Content goes in Header, Content and Actions groups, and only
Content scrolls.

Opening and closing are animated with Material 3 motion, and respect reduced
motion. The native dialog supplies the top layer, scrim, focus containment and
focus restoration; Escape and scrim dismissal follow the dialog's settings.

When a part is created in the Dialog Surface area, the Site Editor offers seven
starting designs: basic dialog, basic dialog with icon, list dialog, full-screen
dialog, bottom sheet, modal side sheet and standard side sheet. The pattern is
only a start; the part made from it is yours to edit.

= Buttons =

A **Dialog Button Group** holds **Dialog Button** and **Dialog Icon Button**
blocks: a Material 3 standard button group. The group sets size, shape,
distribution and selection; each button carries its icon, toggle and disabled
state. The buttons render as WordPress buttons, so a theme's button styles apply
to them, and they convert to and from `core/button`.

Each button's **Action** decides what it does, and the markup and ARIA follow
from that choice: a command, a form submit or reset, opening or closing a
Dialog Surface, or opening or closing a Navigation Overlay template part.

A toggle, or a button whose surface is open, can swap to a selected icon, fill
a variable icon font, and move between the two with a fade, rotation or scale.
Preview on hover shows that state under the pointer and keyboard focus.

The **Dialog Icon** block draws an icon from the theme's icon font or the
WordPress Icon Registry; the same renderer paints the icons inside the buttons.

= Per-page hubs =

**Object Media Dialog** and **Post Quick View** are single dialogs placed once
per page. Blocks in a feed open them with the selected item, so a feed never
renders one hidden dialog per post. Post Quick View fetches the post on demand
from this site's REST API; its trigger currently links to the post's comments.

== Installation ==

1. Upload and activate the plugin. WordPress 7.1 or later is required.
2. In the Site Editor, create a template part in the **Dialog Surface** area and
   pick a starting design.
3. Insert a **Dialog Button Group**, set a button's **Action** to open a Dialog
   Surface, and choose the part.

The buttons work with any block theme and carry their own fallbacks. Dialog
surfaces read the Material 3 colour, shape and motion tokens that Axismundi
Theme provides; on another theme, define those `--md-sys-*` tokens or the
surfaces render without their colours, corners and motion.

== Frequently Asked Questions ==

= Does this plugin contact an external service? =

No. Everything runs in WordPress with the native `<dialog>` element and the
WordPress Interactivity API. Post Quick View requests only this site's own REST
endpoint, which returns published, publicly viewable posts.

= Where did the Sheets, Sheet and Dialog blocks from 0.2 go? =

They were replaced by Dialog Surface template parts in 0.3.0. See the
changelog.

== Changelog ==

= 0.3.0 =

* Dialogs and sheets are now **Dialog Surface** template parts. The plugin
  registers the Dialog Surface area, and its **Dialog** block is the native
  `<dialog>` at the part's root, presented as a basic or full-screen dialog, a
  bottom sheet or a side sheet.
* A part is opened by a button whose Action names it, rendered once at the end
  of the page however many buttons open it, and closed by a button, Escape or
  the scrim as its settings allow.
* Seven starting designs for new parts, laid out in Header, Content and Actions
  groups with Material 3 spacing.
* Material 3 motion for opening and closing each presentation, with reduced
  motion respected.
* Full-screen dialogs become basic dialogs from 600px up.
* Bottom sheets: a drag handle that expands and collapses the sheet by click,
  keyboard, drag or fling, keeping Material 3's top margin when expanded.
* Standard side sheets open below the admin bar and resize or move the page to
  make room; on compact screens they open as modals.
* **Removed:** the Sheets, Sheet, Dialog (0.2), Dialog Close and Dialog Title
  blocks, and the theme template parts they used. Content saved with them
  renders nothing; rebuild it as a Dialog Surface part opened by a Dialog
  Button.
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
* Icon transition moves a button's icon between its states with a fade,
  rotation or scale; rotation alone can turn a single icon, such as a plus into
  a close. Preview on hover shows the selected state under hover and keyboard
  focus.
* Dialog Icon Button shows a Material 3 plain tooltip on hover and focus,
  carrying the accessible name it already has. The tooltip is a popover, so it
  paints above a dialog, and it is shown in the editor as well.
* Buttons convert between the three blocks, and to and from `core/button`,
  keeping everything both sides understand.
* **Action** says what a button is for, and the markup follows from it: a plain
  command, a form submit or reset, opening or closing a **Dialog Surface**, or
  opening or closing a **Navigation Overlay**. Advanced shows the element the
  choice produces, read-only.
* Opening a Navigation Overlay renders the chosen `navigation-overlay` template
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
