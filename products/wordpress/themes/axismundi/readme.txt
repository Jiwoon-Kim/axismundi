=== Axismundi ===
Contributors: kimjiwoon
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.18
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: block-patterns, block-styles, blog, custom-colors, custom-logo, custom-menu, editor-style, featured-images, full-site-editing, full-width-template, template-editing, threaded-comments, translation-ready, wide-blocks

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

== Companion Plugins ==

Axismundi is a complete block theme on its own. These optional plugins, all
hosted on WordPress.org, extend particular surfaces and share the theme's
Material Design 3 tokens, so they inherit its colour, shape, and motion rather
than carrying a second visual system.

* Axismundi Theme Switcher — a light/dark/auto control. The theme reads the same
  attribute in the editor canvas and on the front end.
  https://wordpress.org/plugins/axismundi-theme-switcher/

* Axismundi Navigation Icons — Material Symbols on Navigation items, with the
  icon-beside-label and icon-above-label layouts the theme styles.
  https://wordpress.org/plugins/axismundi-navigation-icons/

* Axismundi Dialogs — Material 3 buttons, icon buttons and button groups, and
  dialogs and sheets kept as template parts. The theme's button styles apply to
  its buttons.
  https://wordpress.org/plugins/axismundi-dialogs/

* Axismundi Table of Contents — a table of contents for long-form posts, styled
  as a Material disclosure.
  https://wordpress.org/plugins/axismundi-table-of-contents/

* Axismundi Media Library — attachments as independent media objects with
  folders, rights, and visibility.
  https://wordpress.org/plugins/axismundi-media-library/

* Axismundi Geodata — geographic taxonomies. The theme's geo_area and geotag
  archive templates are delegated to this plugin when it is active.
  https://wordpress.org/plugins/axismundi-geodata/

* Axismundi Map — map views for those geographic archives.
  https://wordpress.org/plugins/axismundi-map/

* Axismundi Korean Font Provider — fills the theme's locale-aware CJK font slot
  for Korean documents.
  https://wordpress.org/plugins/axismundi-korean-font-provider/

* Axismundi Japanese Font Provider — the same slot, for Japanese documents.
  https://wordpress.org/plugins/axismundi-japanese-font-provider/

== Changelog ==

= 0.1.18 =
* Remove the six Sheet and Dialog template parts. The Axismundi Dialogs plugin
  retired the blocks they were built from; its dialogs and sheets are now
  template parts started from the plugin's own patterns.
* Offer the Tonal, Outlined, Text and Elevated button styles to the Dialogs
  plugin's Dialog Button block, and Tonal and Outlined to its Dialog Icon
  Button, instead of to the retired Dialog and Sheet blocks.
* Keep buttons saved with the older Outlined style class drawn as outlined
  buttons.
* Give the bundled Material Symbols font its default variation settings: fill,
  weight, grade and optical size.

Earlier releases are listed in changelog.txt.

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
Source: https://github.com/Jiwoon-Kim/axismundi/blob/main/products/wordpress/themes/axismundi/screenshot.png

The screenshot is an original capture of the theme rendering and does not use
third-party photography.
