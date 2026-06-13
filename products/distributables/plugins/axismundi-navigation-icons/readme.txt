=== Axismundi Navigation Icons ===
Contributors: kimjiwoon
Tags: navigation, menu, icons, block, editor
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Add a Material Symbols leading icon to navigation items (link, submenu, home) for Axismundi.

== Description ==

Axismundi Navigation Icons is an authoring plugin: it adds a "Navigation Icon"
panel to the block sidebar for navigation items, where you type a Material
Symbols ligature name (e.g. `home`, `article`, `category`, `sell`, `tag`,
`folder`). The icon is inserted before the item label.

Supported blocks:

* Navigation Link (`core/navigation-link`) — also covers the page / post /
  category / tag / custom link variants, which are the same block. Page links
  default to `pages`, category links to `category`, and tag links to `label`
  when no custom icon is authored.
* Submenu (`core/navigation-submenu`).
* Home Link (`core/home-link`) — a single "Show home icon" toggle, since its
  meaning is fixed.
* Page List (`core/page-list`) — generated page-list items default to the
  `pages` Material Symbols icon.

The Axismundi theme keeps the Material Design 3 Navigation Bar / Rail / Menu
spec, the Material Symbols font (registered in theme.json) and the
`.material-symbols-outlined` box contract (icons.css). This plugin owns only the
icon data and its insertion. The disclosure arrows and submenu popover styling
remain theme-owned.

== Installation ==

1. Install and activate an Axismundi-family theme.
2. Upload and activate this plugin.
3. Select a navigation item, open the "Navigation Icon" panel in the sidebar,
   and type a Material Symbols name (or toggle the home icon).

== Frequently Asked Questions ==

= Can this plugin be used without the Axismundi theme? =

The icon name is still inserted, but the glyph renders only when the active
theme provides the Material Symbols font and the `.material-symbols-outlined`
contract. Without it the ligature name degrades gracefully to plain text.

= Where can I find icon names? =

Browse fonts.google.com/icons and use the lowercase name, e.g. `shopping_cart`.

== Changelog ==

= 0.1.0 =

* Initial release: a "Navigation Icon" sidebar control for Navigation Link,
  Submenu, and Home Link, with a live preview in the editor canvas and a
  front-end `render_block` insertion of the Material Symbols span.
* The icon-box contract (1em box, overflow clip, ligature fallback) is inherited
  from the Axismundi theme's icons.css; the plugin only adds icon-to-label
  alignment on the front and re-creates the glyph via `::before` in the editor
  canvas.
