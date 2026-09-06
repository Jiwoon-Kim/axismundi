=== Axismundi Theme Controls ===
Contributors: kimjiwoon
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: color, material-design, block-theme

Switch the site between Material Design 3 colour schemes, and remember what the
reader picked.

== Description ==

A reader picks a colour scheme and the whole site follows: brand colours,
surfaces, outlines. The choice is kept in a first-party cookie and applied
before the next page paints, so returning does not flash the previous scheme.

Four schemes ship — Blue, Cyan, Green and Orange — alongside whatever the
active theme's own scheme is. Every one is a complete Material Design 3 tonal
scheme rather than a recolouring: the primary, secondary, tertiary, neutral and
neutral-variant families are all derived together, so the surfaces shift with
the accents instead of staying behind.

The error colour never changes. Material Design holds it at a fixed hue across
schemes so a warning still reads as a warning.

= What it needs from a theme =

A theme whose colours are Material Design 3 reference tokens —
`--md-ref-palette-*` on `:root`, with `--md-sys-color-*` roles reading them.
Axismundi is built that way. Under a theme that hardcodes its colours instead,
this plugin changes nothing and breaks nothing.

= Status =

This is an early, deliberately small version. It proves the mechanism with one
control; it is not yet a block, has no settings screen, and cannot yet take a
colour of your own.

== Installation ==

1. Upload and activate the plugin.
2. The control appears in the lower corner of the front end.

== Frequently Asked Questions ==

= Can I add my own colour? =

Not yet. The schemes that ship are generated ahead of time from Material
Design's published palettes. Generating one from an arbitrary colour needs
Material's own colour library on the page, which is a later decision rather
than a missing line of code.

= Does it change what visitors see by default? =

No. Until someone picks a scheme, the theme's own colours show.

== Changelog ==

= 0.1.0 =
* First version. Four Material Design 3 schemes, a scheme control on the front
  end, and the choice remembered between visits.

== Copyright ==

Axismundi Theme Controls, Copyright 2026 KIM JIWOON.
Distributed under the terms of the GNU General Public License, version 3 or
later.

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

The colour values in assets/schemes.css are derived from Material Design 3's
published palettes and specifications.
Copyright Google LLC.
License: Apache License 2.0
License URI: https://www.apache.org/licenses/LICENSE-2.0
Source: https://m3.material.io/styles/color/system/overview
