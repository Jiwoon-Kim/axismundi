# Axismundi VQA — widget blocks baseline

> Phase 1 reverse-direction baseline (CLAUDE.md #10): how WordPress core
> **widget-family** blocks render on the blank Axismundi theme before any M3 token,
> Global Styles, or block-binding work. Block-rooted (`wp:*` / `.wp-block-*`).
> Harness: `patterns/vqa-widgets.php`, seeded as the `vqa-widgets` page. The
> data-driven widgets need content, so the seed also creates a `News` category, a
> `demo` tag, 3 demo posts, and one comment.
> Captured 2026-06-07 on the workspace env (`localhost:8884`, theme `axismundi`).
> Dev-only, excluded from the ZIP.

## Headline finding

Editor block validation: **0 invalid blocks**. Front render: every widget shows
its data — the blank theme adds no chrome, so all are UA serif / `#000` / blue
underlined links over core block-library list/table markup.

- **search**: label + text input + submit button (core block-library default).
- **latest-posts**: 3 items, dated links.
- **latest-comments**: avatar + author link + excerpt + date.
- **archives**: month list with counts; **categories**: News / Uncategorized + counts.
- **tag-cloud**: the `demo` tag; **calendar**: a `<table>` month grid.
- **page-list**: the site's pages (Prose/Text/Design/Media/Widgets/Sample).
- **rss**: external `wordpress.org/news` feed items load (title links + excerpt + date).
- **social-links**: icon list (wordpress, github).
- **custom-html**: raw markup pass-through; **loginout**: a login/logout link.

These are mostly lists/tables of links — the M3 work later is typography + list
de-prose + the search input / button binding + social icon treatment.

## Computed baseline (front render, page 20)

| Block | rendered |
|---|---|
| search | input + button (core default) |
| latest-posts | 3 dated post links |
| latest-comments | avatar + author + excerpt + date |
| archives | 1 month + count |
| categories | 2 categories + counts (hierarchy) |
| tag-cloud | 1 tag link |
| calendar | month `<table>` |
| page-list | 6 page links |
| rss | 3 external feed items (title/excerpt/date) |
| social-links | 2 icons |
| custom-html / loginout | raw HTML / login link |

Screenshot of record: `tmp/baseline-widgets.png` (dev artifact, not committed).
Re-run: seed `patterns/vqa-widgets.php` (with demo content) → the `vqa-widgets`
page and re-capture.
