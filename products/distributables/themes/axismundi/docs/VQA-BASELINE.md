# Axismundi VQA — blank/core baseline

> Phase 1 reverse-direction baseline (CLAUDE.md #10): how WordPress core text
> primitives render on the **blank Axismundi theme** (Create Block Theme defaults,
> no M3 binding yet), captured *before* any token/Global-Styles work so Phase 2/4
> changes are measured against a real "before". Harness: `patterns/vqa-prose.php`
> seeded as the `vqa-prose` page. Dev-only (excluded from the ZIP).
> Captured 2026-06-07 on the workspace env (localhost:8884, theme `axismundi`).

## Headline finding

The blank theme renders core blocks in **browser defaults**, not even its own
registered font:

- **font-family: Times New Roman** (browser default serif) for all text;
  `monospace` for code/preformatted. The CBT `theme.json` *registers* a System
  Font family but never sets it as the default (`styles.typography.fontFamily` is
  absent — there is no `styles` block at all), so nothing overrides the UA serif.
- **color: rgb(0,0,0)** pure black; **body background: transparent** (UA white).
- **line-height: normal** everywhere (no theme line-height).
- The only theme.json value that *is* applied: **content width 620px**.
- Headings/pullquote/table/code carry **core block-library** defaults only.

So the M3 work in Phase 2 (token + Global Styles typography/color) and Phase 4
(block binding) is what turns this serif/black/UA render into the Material type
scale, Roboto/Noto families, and M3 color roles.

## Computed baseline (1100px viewport, 620px content)

| Block | font-size | line-height | weight | family | color | margin-top |
|---|---|---|---|---|---|---|
| h1 | 32px | normal | 700 | Times New Roman | #000 | 0 |
| h2 | 24px | normal | 700 | Times New Roman | #000 | 24px |
| h3 | 18.72px | normal | 700 | Times New Roman | #000 | 24px |
| h4 | 16px | normal | 700 | Times New Roman | #000 | 24px |
| h5 | 13.28px | normal | 700 | Times New Roman | #000 | 24px |
| h6 | 10.72px | normal | 700 | Times New Roman | #000 | 24px |
| p (body) | 16px | normal | 400 | Times New Roman | #000 | 24px |
| drop-cap p | 16px | normal | 400 | Times New Roman | #000 | 24px |
| ul/ol li | 16px | normal | 400 | Times New Roman | #000 | 0 |
| quote p | 16px | normal | 400 | Times New Roman | #000 | 0 |
| pullquote p | 24px | 38.4px | 400 | Times New Roman | #000 | 0 |
| table th | 16px | normal | 700 | Times New Roman | #000 | 0 |
| table td | 16px | normal | 400 | Times New Roman | #000 | 0 |
| code | 13px | normal | 400 | monospace | #000 | 0 |
| preformatted | 13px | normal | 400 | monospace | #000 | 24px |
| verse | 16px | normal | 400 | Times New Roman | #000 | 24px |
| separator | width 620, height 0, transparent bg | | | | | |

Screenshot of record: `tmp/baseline-prose-axismundi.png` (dev artifact, not
committed). Re-run: seed `patterns/vqa-prose.php` → `?page_id=<vqa-prose>` and
re-capture to compare a post-binding render against this table.
