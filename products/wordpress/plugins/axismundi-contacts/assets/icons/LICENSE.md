# Material Symbols

The SVG files in this directory are from Google's Material Symbols, licensed under the Apache
License 2.0.

* Source: https://github.com/google/material-design-icons
* Licence: https://www.apache.org/licenses/LICENSE-2.0

They are the `outlined` style at 24dp, `FILL 0`, `wght 400`, `GRAD 0`, `opsz 24` — one style for the
whole set, so that two icons beside each other never look like they came from different places.

Two changes were made to each file, and nothing else:

* `fill` is `currentColor` rather than the literal grey the downloads carry, so an icon takes the
  colour of the thing it sits in. An icon that cannot is a picture of an icon.
* The `width` and `height` attributes are removed. WordPress's icon registry supplies its own when
  it renders, so carrying a second pair in the file would mean two answers to one question -- and
  the one in the file would be the one nobody could change.

The paths themselves are untouched.
