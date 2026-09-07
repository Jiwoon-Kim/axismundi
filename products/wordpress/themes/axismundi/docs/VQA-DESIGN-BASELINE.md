# Axismundi VQA — design blocks baseline

> Phase 1 reverse-direction baseline (CLAUDE.md #10): how WordPress core
> **design-family** blocks render on the blank Axismundi theme before any M3 token,
> Global Styles, or block-binding work. Block-rooted (`wp:*` / `.wp-block-*`).
> Harness: `patterns/vqa-design.php`, seeded as the `vqa-design` page.
> Captured 2026-06-07 on the workspace env (`localhost:8884`, theme `axismundi`).
> Dev-only, excluded from the ZIP.

## Headline finding

`invalidBlocks: 0` — all design blocks render. Unlike text/prose (pure UA serif),
the design family carries **WordPress core block-library defaults**, so even the
blank theme already styles buttons and lays out columns/flex:

- **Buttons get a real default look** (core block library), not UA:
  - Filled: background `rgb(50,55,60)` (core contrast), text white, **fully
    rounded `border-radius: 9999px` (pill)**, padding `12.67px 23.33px`, 16px.
  - Outline (`is-style-outline`): transparent bg, `2px` solid black border, black text.
  - The M3 button binding (later) reshapes fill/outline colour, radius, and state.
- **Layout blocks** use core's flex/grid with a **24px gap** default:
  - Columns: `display:flex`, gap 24px, 3 equal columns (stack on narrow).
  - Row (group flex/horizontal): gap 24px, nowrap.
  - Stack (group flex/vertical): gap 24px.
  - Group (constrained): bare container, no chrome.
- **Separator**: width = content (620px), height 0, `border-top: 2px rgb(128,128,128)`.
- **Spacer**: honors its `40px` height.
- Text inside still UA serif / `#000` / blue links; content width 620px.

So for the design family the "before" is core-block-library styling (notably the
pill buttons + 24px gaps), not raw UA — the M3 work re-skins these to Material
button shapes/colours and the token spacing scale.

## Computed baseline (1100px viewport)

| Block | key computed values |
|---|---|
| button (filled) | bg rgb(50,55,60) · text #fff · radius 9999px · padding 12.67/23.33px · 16px Times |
| button (outline) | bg transparent · text #000 · border 2px #000 |
| button (50% width) | `is-style` none, `.wp-block-button__width-50` → 50% column width |
| columns | flex · gap 24px · 3 columns |
| row (group flex) | flex-row · gap 24px · nowrap |
| stack (group flex) | flex-column · gap 24px |
| group (constrained) | bare container, no background/border |
| separator | width 620 · height 0 · border-top 2px rgb(128,128,128) |
| spacer | height 40px |
| more / page-break | render as content-flow markers (more split; nextpage paginates) |

Screenshot of record: `tmp/baseline-design.png` (dev artifact, not committed).
Re-run: seed `patterns/vqa-design.php` → the `vqa-design` page and re-capture.
