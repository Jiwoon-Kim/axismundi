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
* Home Link (`core/home-link`) — shows the `home` icon by default, with a
  "Show home icon" toggle to opt out.
* Page List (`core/page-list`) — shows the `pages` icon by default for generated
  page items when the list is placed inside a Navigation block, with a "Show item
  icons" toggle to opt out.

A `core/navigation` style variation, "Vertical item", stacks the icon above the
label (M3 Navigation Bar / Rail vertical item) and moves the active indicator
onto a 56x32 icon slot; the unstyled default keeps the icon beside the label.

The Axismundi theme keeps the Material Design 3 navigation spec and the item
baseline (pill, state layer, active indicator), the Material Symbols font
(registered in theme.json) and the `.material-symbols-outlined` box contract
(icons.css). This plugin owns the icon data and its insertion, the item-layout
style variation, the front-end icon-click delegation, and — for the items it
restructures — the submenu disclosure arrow.

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

* "Navigation Icon" sidebar control for Navigation Link and Submenu (type a
  Material Symbols name), default-on Home Link and Page List icons, and toggles
  to opt out of those fixed defaults.
* Page, category and tag links get a semantic default icon (pages / category /
  label) when none is authored; an explicit empty value opts out, with a reset
  to the default.
* A "Vertical item" core/navigation style variation: icon above the label with
  the active indicator on a 56x32 icon slot. The default layout keeps the icon
  beside the label.
* `render_block` restructures an icon-bearing item into an icon box + body at the
  `<li>` level; a small front-end script forwards an icon click to the item link,
  and the plugin renders the submenu disclosure arrow for restructured triggers.
* The icon-box contract (1em box, overflow clip, ligature fallback) is inherited
  from the Axismundi theme's icons.css.
