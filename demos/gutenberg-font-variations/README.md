# Gutenberg font variations demo

Playground fixture for the Gutenberg `font-variation-settings` prototype (issue [WordPress/gutenberg#83148](https://github.com/WordPress/gutenberg/issues/83148)). It is test data, not an Axismundi product.

## What it sets up

- **Capability:** Roboto Flex, with every axis of its `fvar` table in the face `axes` list.
- **Policy:** `settings.typography.fontVariations`, keyed by font family slug and then by axis tag, exposes `GRAD` (−200 to 150) and `opsz` for Roboto Flex. Heading also exposes `XTRA`, named "Counter width"; block settings replace the site's for that block, so Heading lists `GRAD` and `opsz` again. The policy also lists `wght` and `FILL`, which are never offered: `wght` has its own property, and the file has no `FILL` axis.
- **Styles:** Roboto Flex as the site font.
- **Content:** a "Font variations demo" post with a Heading, two Paragraphs and a List. `/wp-admin/?font-variations-demo` opens it in the editor.

## What to check

1. Paragraph: Styles → Font variations shows Grade and Optical size only.
2. Heading: it also shows Counter width.
3. List: no Font variations panel, as the block has no support.
4. Set a Grade, then pick another font in Typography: the axis values are cleared and the panel goes away.
5. Site Editor → Styles → Typography → Text, and Blocks → Heading: the same panel in Global Styles.

## Files

| File | |
|-|-|
| `demo-plugin/gutenberg-font-variations-demo.php` | The fixture plugin. Playground installs this directory. |
| `demo-plugin/assets/RobotoFlex.woff2` | The font. |
| `demo-plugin/assets/OFL.txt` | The font license. |

The font sits inside `demo-plugin/` so that installing that one directory brings the font and its license with it.

## Font

- **Font:** Roboto Flex
- **Source:** Google Fonts, [google/fonts `ofl/robotoflex`](https://github.com/google/fonts/tree/main/ofl/robotoflex), original file `RobotoFlex-VariableFont_GRAD,XOPQ,XTRA,YOPQ,YTAS,YTDE,YTFI,YTLC,YTUC,opsz,slnt,wdth,wght.ttf`
- **Processing:** converted to WOFF2 with `pyftsubset`, format conversion only: no glyph subset, all 13 variable axes kept. The same file as the Axismundi theme's `assets/fonts/roboto-flex/axismundi-roboto-flex.woff2` (SHA-256 `7b949602f09c57b8…`).
- **License:** SIL Open Font License 1.1, see `demo-plugin/assets/OFL.txt`.
- **Purpose:** Gutenberg `font-variation-settings` Playground fixture.

## Playground

Gutenberg itself comes from the pull request's CI build ZIP: Playground cannot build a Gutenberg source branch. This directory is installed with a `git:directory` resource pinned to a commit SHA, so later changes here do not change a published demo.

The [blueprint](blueprint.json) installs the fixture. Load it with the target
Gutenberg PR through Playground's `gutenberg-pr` query parameter.
