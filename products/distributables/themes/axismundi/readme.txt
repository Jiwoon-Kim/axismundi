=== Axismundi ===
Contributors: kimjiwoon
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.9
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: block-patterns, custom-colors, custom-logo, editor-style

Axismundi is a Material Design 3 block theme that binds native WordPress core
blocks to a Material-token design language.

== Description ==

Axismundi maps WordPress core blocks onto a Material Design 3 token system —
color, typography, shape, motion, and elevation — built first-class through
theme.json / Global Styles. It is developed toward an ActivityPub-based social
CMS.

== Installation ==

1. Upload and activate Axismundi from Appearance > Themes.
2. Open Appearance > Editor to customize templates and global styles.

== Recommended setup ==

Axismundi does not hard-code a front page. For a curated homepage, create a
Page, insert the "Front page — magazine" block pattern (a Featured grid over a
Latest feed), and set it under Settings > Reading > "Your homepage displays" >
A static page, choosing that page as the Homepage and any page as the Posts
page. home.html then renders the posts index (the reader feed) on the Posts
page. With "Your latest posts" selected instead, home.html serves the front.
Category, tag, and date archives share the axismundi/query-feed feed body via
archive.html.

== Changelog ==

= 0.1.9 =
* Delegate the complete geo_area and geotag archive templates to the Geodata plugin while retaining the theme's taxonomy presentation styles.

= 0.1.8 =
* Add the posts-index home template with Post Quick View actions and restore its footer template part.
* Add reusable reader-feed and static front-page magazine patterns.

= 0.1.7 =
* Load the bundled Material Symbols font explicitly when WordPress omits its unused font-family preset from generated global styles.

= 0.1.6 =
* Add editable Sheet and Dialog template parts for the Axismundi Dialogs companion plugin.
* Provide Material 3 layouts for side sheets, bottom sheets, basic dialogs, list dialogs, and full-screen dialogs.
* Hide disclosure icons in always-expanded Side Sheet navigation.

= 0.1.5 =
* Redesign the navigation overlay and header: a full-screen drawer whose layout, surface, and close control are owned by core and block markup.
* Remove the dedicated overlay stylesheet now that the drawer is delegated to core.
* Render the Navigation bar/rail/menu skin in the block-editor canvas, where core outputs the block as a div rather than a nav.
* Stop the vertical rail state layer from leaking into a nested overlay navigation, and drop the redundant rail container background that clipped neighbouring block shadows.
* Mark an exact same-site custom Navigation Link as the current page.

= 0.1.4 =
* Keep responsive navigation controls visible while long menus scroll.
* Let Page List inherit Navigation typography and isolate always-open submenu row states from their inline sublists.
* Remove promotional links from the default footer.
* Add a post-with-sidebar template and refine single-post article spacing.

= 0.1.3 =
* Replace the hard-coded Korean font fallback with locale-aware CJK sans/serif
  slots that independent regional font plugins can fill.
* Add dedicated geo_area and geotag archive templates with Query Map View
  integration when the companion Geodata and Map plugins are active.
* Add location-marked taxonomy chips and refine archive/query patterns with
  enhanced pagination and clearer result layouts.
* Add an opt-in Feed header style for the core RSS block, showing the feed icon,
  linked title, and description above the existing Material collection cards.

= 0.1.2 =
* Add Search, Archive, Page, and 404 block templates and refine the footer.
* Add Material 3 styling for post navigation, pagination, taxonomy blocks, and
  taxonomy/archive dropdown selects.
* Add progressive enhancement for customizable select pickers while preserving
  native mobile and unsupported-browser behavior.
* Refine card patterns, raw media alignment, and comment thread connectors.

= 0.1.1 =
* Document the source URL, copyright, license, and license URL for every bundled
  font and icon asset.
* Document the source and license for screenshot.png.
* Synchronize the public theme and runtime asset versions.

= 0.1.0 =
* Initial release of the Material Design 3 block theme and token system.
* Global Styles, block styles, patterns, responsive navigation, navigation
  overlay, single-post layouts, comments, post navigation, and local font assets.
* Light, dark, and automatic color-scheme support when used with a compatible
  switcher.

== Copyright ==

Axismundi WordPress Theme, Copyright 2026 KIM JIWOON.
Axismundi is distributed under the terms of the GNU General Public License,
version 3 or later.

This program is free software: you can redistribute it and/or modify it under the
terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

This theme bundles the following third-party resources:

== Fonts ==

The original font files were converted to WOFF2 for web delivery.

Roboto Flex
Copyright 2017 The Roboto Flex Project Authors.
License: SIL Open Font License, 1.1
License URI: https://openfontlicense.org/open-font-license-official-text/
Source: https://github.com/google/fonts/tree/main/ofl/robotoflex

Roboto Serif
Copyright 2020 The Roboto Serif Project Authors.
License: SIL Open Font License, 1.1
License URI: https://openfontlicense.org/open-font-license-official-text/
Source: https://github.com/google/fonts/tree/main/ofl/robotoserif

Roboto Mono
Copyright 2015 The Roboto Mono Project Authors.
License: SIL Open Font License, 1.1
License URI: https://openfontlicense.org/open-font-license-official-text/
Source: https://github.com/google/fonts/tree/main/ofl/robotomono

== Icons ==

Material Symbols Outlined
Copyright Google LLC.
License: Apache License 2.0
License URI: https://www.apache.org/licenses/LICENSE-2.0
Source: https://github.com/google/material-design-icons/tree/master/variablefont

== Screenshot ==

Axismundi theme demonstration screenshot
Copyright 2026 Jiwoon Kim.
License: GNU General Public License v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Source: https://github.com/Jiwoon-Kim/axismundi/blob/main/products/distributables/themes/axismundi/screenshot.png

The screenshot is an original capture of the theme rendering and does not use
third-party photography.
