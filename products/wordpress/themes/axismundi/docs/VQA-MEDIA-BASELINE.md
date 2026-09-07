# Axismundi VQA — media blocks baseline

> Phase 1 reverse-direction baseline (CLAUDE.md #10): how WordPress core
> **media-family** blocks render on the blank Axismundi theme before any M3 token,
> Global Styles, or block-binding work. Block-rooted (`wp:*` / `.wp-block-*`).
> Harness: `patterns/vqa-media.php`, seeded as the `vqa-media` page (images via
> picsum.photos; audio/video via small sample sources).
> Captured 2026-06-07 on the workspace env (`localhost:8884`, theme `axismundi`).
> Dev-only, excluded from the ZIP.

## Headline finding

`invalidBlocks: 0`, all 6 images load. The media family is mostly core
block-library layout; the blank theme adds nothing yet.

- **Block image gets a fit cap**: `.wp-block-image img` → `max-width: 100%`. This
  is the block-rooted counterpart to the prose baseline, where a raw `<img>` had
  `max-width: none` and overflowed — confirms the migration split: block class is
  constrained by core, raw HTML is not.
- **Gallery**: `display:flex`, gap 24px, 3 columns.
- **Cover**: honors `min-height: 320px`, flex centering, dimmed background image +
  overlay heading (white text renders over the dim layer).
- **Media & Text**: `display:grid`, 50/50 columns (`310px 310px` at 620 content).
- **Audio / Video**: native UA player chrome; video honors the picsum `poster`.
- **File**: download button is core block-library (bg `rgb(50,55,60)`, radius
  `25.6px`) — note this differs from the core/buttons pill (9999px); both get
  re-skinned to the M3 button later.
- Captions/headings still UA serif; content width 620px.

## Computed baseline (1100px viewport)

| Block | key computed values |
|---|---|
| image | rendered 600px · **max-width 100%** · figcaption UA serif |
| gallery | flex · gap 24px · 3 columns |
| cover | min-height 320px · flex · dim background + overlay heading |
| media-text | grid · 310px 310px (50/50) · stacks on mobile |
| audio | native UA controls |
| video | native UA controls · picsum poster |
| file | link + download button (bg rgb(50,55,60), radius 25.6px) |

Screenshot of record: `tmp/baseline-media.png` (dev artifact, not committed).
Re-run: seed `patterns/vqa-media.php` → the `vqa-media` page and re-capture.
