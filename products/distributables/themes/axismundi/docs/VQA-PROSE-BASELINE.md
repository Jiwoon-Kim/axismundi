# Axismundi VQA — Prose baseline (raw-HTML element layer)

> Phase 1 reverse-direction baseline (CLAUDE.md #10): how **raw HTML elements**
> render on the blank Axismundi theme (Create Block Theme defaults, no M3 binding),
> captured *before* any token / Global-Styles / `.federated-content` work so later
> changes are measured against a real "before".
> Harness: `patterns/prose-vqa.php` (a single Custom HTML block of raw elements),
> seeded as the `prose-vqa` page. Dev-only (excluded from the ZIP).
> Captured 2026-06-07 on the workspace env (localhost:8884, theme `axismundi`).

## Terminology (locked)

```txt
Prose VQA       = raw HTML elements (Custom HTML, no .wp-block-* wrappers)
                  → markdown / federated remote HTML / raw prose rendering
                  → styled later only inside a prose scope, e.g. .federated-content
                  → NOT a block-binding target
Core Block VQA  = wp:* blocks (wp:paragraph, wp:heading, wp:list, ...)
                  → WordPress core block → M3 token binding
                  → a later phase; not built yet
```

This file is the **Prose** baseline. The Core Block baseline is a separate
document for when that phase begins.

## Headline finding

On the blank theme, raw HTML renders in **pure browser (UA) defaults** — the CBT
`theme.json` registers a System Font but never sets a default `fontFamily` (there
is no `styles` block at all), so nothing overrides the UA serif:

- **font-family: Times New Roman** (UA serif) for text; `monospace` for code/pre/kbd.
- **color: #000**; **body background: transparent** (UA white); **line-height: normal**.
- The only theme.json value applied: **content width 620px**.
- **Links: UA blue `rgb(0,0,238)`, underlined.**
- **`<hr>`: height 0, border-top 1px `rgb(128,128,128)`.**
- **`<img>`: `max-width: none`** — a raw image renders at intrinsic size and would
  overflow a narrow column (the prose layer has no fit-to-container cap yet).
- `<mark>`: UA yellow highlight. `<sub>/<sup>/<small>/<q>/<var>/<samp>/<time>/
  <dfn>/<b>/<i>/<u>/<details>/<summary>/<picture>/<audio>/<video>/<iframe>` all
  render at their UA defaults (player chrome, disclosure triangle, etc.).

So the foundation work turns this serif/black/UA render into the intended
sans / Material type scale / color roles — and any prose styling must stay scoped
(no broad leak), since this surface is the federated/remote-content lane.

## Computed baseline (1100px viewport, 620px content)

| Element | font-size | line-height | weight | family | color | margin-top |
|---|---|---|---|---|---|---|
| h1 | 32px | normal | 700 | Times New Roman | #000 | 0 |
| h2 | 24px | normal | 700 | Times New Roman | #000 | 0 |
| h3 | 18.72px | normal | 700 | Times New Roman | #000 | 24px |
| h4 | 16px | normal | 700 | Times New Roman | #000 | 24px |
| h5 | 13.28px | normal | 700 | Times New Roman | #000 | 24px |
| h6 | 10.72px | normal | 700 | Times New Roman | #000 | 24px |
| p | 16px | normal | 400 | Times New Roman | #000 | 24px |
| ul/ol li | 16px | normal | 400 | Times New Roman | #000 | 0 |
| dt / dd | 16px | normal | 400 | Times New Roman | #000 | 0 |
| blockquote | 16px | normal | 400 | Times New Roman | #000 | 24px |
| cite | 16px | normal | 400 | Times New Roman | #000 | 0 |
| pre / pre code | 13px | normal | 400 | monospace | #000 | 24 / 0 |
| table th | 16px | normal | 700 | Times New Roman | #000 | 0 |
| table td | 16px | normal | 400 | Times New Roman | #000 | 0 |
| figcaption | 16px | normal | 400 | Times New Roman | #000 | 0 |
| kbd | 13px | normal | 400 | monospace | #000 | 0 |
| a (link) | — | — | — | — | rgb(0,0,238), underline | — |
| hr | height 0, border-top 1px rgb(128,128,128), margin-top 24px | | | | | |
| img | rendered 600px, **max-width: none** | | | | | |

Screenshot of record: `tmp/prose-vqa-final.png` (dev artifact, not committed).
Re-run: seed `patterns/prose-vqa.php` → the `prose-vqa` page and re-capture to
compare a post-styling render against this table.

## TT5 reference snapshot

Captured by temporarily activating `twentytwentyfive` on the workspace env (against
an earlier core-block specimen, hence the pullquote line), then switching back to
`axismundi`. Not a target style — a useful "core-quality block theme" reference for
how a polished default theme treats the same content before Axismundi's M3 Global
Styles are applied.

Key differences from the blank Axismundi UA baseline:

- TT5 applies a real theme font: **Manrope, sans-serif**.
- Body text is already large and light: `21.7632px / 30.4685px`, weight `300`,
  letter-spacing `-0.1px`, color `rgb(17,17,17)`.
- The content column is **645px** wide at a 1280px viewport.
- Headings are theme-scaled rather than UA defaults:
  - h1: `47.1968px / 53.0964px`, weight `400`
  - h2: `31.7632px / 35.7336px`, weight `400`
  - h3: `21.7632px / 24.4836px`, weight `400`
- Pullquote is promoted to h1-like scale: `47.1968px / 56.6362px`.
- Preformatted code uses `Fira Code, monospace`, `17.8816px / 25.0342px`,
  background `rgb(251,250,243)`.

Snapshot file: `tmp/baseline-prose-tt5.png` (dev artifact, not committed).
