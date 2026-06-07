# Axismundi VQA — core text blocks baseline

> Phase 1 reverse-direction baseline: how WordPress **core text blocks** render on
> the blank Axismundi theme before any M3 token, Global Styles, or block-binding
> work. This is block-rooted (`wp:*` / `.wp-block-*`) and intentionally separate
> from `patterns/prose-vqa.php`, which covers raw HTML elements.
>
> Harness: `patterns/vqa-text.php`, seeded as the `vqa-text` page.
> Captured 2026-06-07 on the workspace env (`localhost:8884`, theme
> `axismundi`). Dev-only, excluded from the ZIP.

## Headline finding

Core text blocks currently render almost exactly like UA defaults:

- **font-family: Times New Roman** for ordinary text and headings.
- **monospace** for `core/code` and `core/preformatted`.
- **color: rgb(0,0,0)** for normal text; links use UA blue
  `rgb(0,0,238)`.
- **line-height: normal** across prose-like text.
- Content width comes from the blank theme layout: **620px** at a 1280px
  viewport.
- The pattern validates cleanly: `invalidBlocks: 0`.

## Coverage

The VQA page covers these core text surfaces:

- headings h1-h6
- paragraph, drop cap, inline formatting, links
- left / center / right text alignment
- unordered, ordered, nested, and segmented-list candidate class
- quote, plain quote candidate class, pullquote
- code, preformatted, verse
- table, striped table
- details / summary
- separator
- footnotes with paired `footnotes` post meta

## Computed baseline (1280px viewport, 620px content)

| Surface | font-size | line-height | weight | family | color | notes |
|---|---:|---|---:|---|---|---|
| body | 16px | normal | 400 | Times New Roman | #000 | UA default |
| post content | 16px | normal | 400 | Times New Roman | #000 | width 620px |
| h1 | 32px | normal | 700 | Times New Roman | #000 | UA heading |
| h2 | 24px | normal | 700 | Times New Roman | #000 | UA heading |
| h3 | 18.72px | normal | 700 | Times New Roman | #000 | UA heading |
| h4 | 16px | normal | 700 | Times New Roman | #000 | UA heading |
| h5 | 13.28px | normal | 700 | Times New Roman | #000 | UA heading |
| h6 | 10.72px | normal | 700 | Times New Roman | #000 | UA heading |
| paragraph | 16px | normal | 400 | Times New Roman | #000 | margin-top 24px |
| link | 16px | normal | 400 | Times New Roman | rgb(0,0,238) | UA link |
| list | 16px | normal | 400 | Times New Roman | #000 | padding-left 40px |
| segmented-list candidate | 16px | normal | 400 | Times New Roman | #000 | no style registered yet |
| quote | 16px | normal | 400 | Times New Roman | #000 | no block-specific chrome yet |
| plain quote candidate | 16px | normal | 400 | Times New Roman | #000 | no style registered yet |
| pullquote | 24px | 38.4px | 400 | Times New Roman | #000 | core block-library |
| code block | 13px | normal | 400 | monospace | #000 | UA/code default |
| preformatted | 13px | normal | 400 | monospace | #000 | UA/code default |
| verse | 16px | normal | 400 | Times New Roman | #000 | verse is not code |
| table | 16px | normal | 400 | Times New Roman | #000 | fixed layout specimen |
| striped table | 16px | normal | 400 | Times New Roman | #000 | no custom token binding yet |
| details | 16px | normal | 400 | Times New Roman | #000 | open specimen |
| separator | 16px | normal | 400 | Times New Roman | gray | UA `hr` |
| footnotes | 16px | normal | 400 | Times New Roman | #000 | 2 items rendered from post meta |

Screenshot of record: `tmp/baseline-vqa-text-axismundi.png` (dev artifact, not
committed).
