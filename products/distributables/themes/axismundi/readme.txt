=== Axismundi ===
Contributors: kimjiwoon
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.13
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

= 0.1.14 =
* Adopt the Material 3 corner radius scale as theme tokens, shared with
  companion plugins.
* Move animation onto Material 3's Expressive motion curves, which the
  specification publishes as the web conversion of its spring physics.
* Complete the state layer set with the disabled opacity, replacing repeated
  literal values across the theme and its companion plugins.
* Give a Buttons block the 12px gap Material 3 specifies for a standard button
  group, while leaving Block Spacing free to override it.
* Apply the Tonal, Text and Elevated button styles to the Dialogs plugin's
  Sheet and Dialog blocks, and add an Outlined style they can share.
* Declare the Material 3 breakpoints as theme.json viewport bands.

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
Source: https://github.com/Jiwoon-Kim/axismundi/blob/main/products/distributables/themes/axismundi/screenshot.png

The screenshot is an original capture of the theme rendering and does not use
third-party photography.
