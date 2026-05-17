# Corpus Source — Upstream Reference

> All upstream sources are **external references** (not stored in monorepo). This keeps the monorepo lightweight and reproducible.

## Pinned upstream repositories

### WordPress Core

- **Version**: 6.9.4
- **SHA**: `97b7f62adb5d8864c3fac554bc7182d9fd754a41`
- **Date**: 2026-03-11
- **Reproduce**:
  ```bash
  git clone https://github.com/WordPress/wordpress-develop.git
  cd wordpress-develop && git checkout 97b7f62adb5d8864c3fac554bc7182d9fd754a41
  ```

### Gutenberg

- **Version**: v23.1.1
- **SHA**: `12c6c76efe7c8f7d83c49655855a7cdb4ff16cdd`
- **Date**: 2026-05-08
- **Sparse-checkout** (only what Axismundi needs):
  ```bash
  git clone --filter=blob:none --no-checkout https://github.com/WordPress/gutenberg.git
  cd gutenberg
  git sparse-checkout init --cone
  git sparse-checkout set docs schemas packages/block-editor packages/blocks packages/core-data
  git checkout 12c6c76efe7c8f7d83c49655855a7cdb4ff16cdd
  ```

### WordPress Developer Handbook

- **Source**: https://developer.wordpress.org/block-editor/
- **Captured**: 2026-05-09 to 2026-05-11
- **Method**: HTML → markdown scrape
- **Files**: 636 markdown originally, refined to 630 (v1.1a)
- **Reproduce**: re-scrape from upstream URL.
  *Note: developer.wordpress.org is a living document; re-scraped result may differ from the captured snapshot. This is why v1.1a-refined is treated as the canonical corpus, not the raw scrape.*

## Why not include raw upstream content directly

- WordPress core (`wordpress-develop`): ~500 MB
- Gutenberg (`gutenberg`): ~300 MB
- Dev handbook raw scrape: ~6.7 MB
- **Total avoided**: ~800 MB

The corpus layer is *about* these sources but doesn't need to *be* them. Tools that need raw upstream clone on-demand.

## What IS stored in the monorepo

The actual corpus content lives at:

- `corpus/refined/dev-handbook-clean/` — v1.1a sanitized dev handbook (630 markdown files, 6.0 MB)
- `corpus/patches/v1_1a/` — transformation history (anchor-based find/replace patches, self-contained)
- `corpus/_meta/` — refinement audit metadata

This is sufficient for reproducibility:

- **Result**: `corpus/refined/dev-handbook-clean/` shows the post-refinement state
- **Transformation**: `corpus/patches/v1_1a/` records every change with verbatim `find` / `replace` / `reason`
- **Verification**: re-running `tools/refine/` against a fresh upstream scrape should produce the same `dev-handbook-clean/` (modulo upstream drift)

If exact 2026-05-09 raw snapshot is ever needed for archaeological purposes, the patches together with the refined result are sufficient to back-derive what the relevant raw lines were (each patch's `find` string is the exact original text).
