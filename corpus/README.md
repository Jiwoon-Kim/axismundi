# Corpus — A layer

Source documents (refined + patches + metadata). Inputs to atlas and core.

- `source/MANIFEST.md` — **external references** (WordPress / Gutenberg / dev-handbook upstream pinning). No content stored in monorepo; clone/scrape from upstream when needed.
- `refined/dev-handbook-clean/` — canonical corpus (v1.1a, 630 markdown files, 5/5 validation PASS)
- `patches/` — transformation history (anchor-based find/replace, self-contained without raw)
- `_meta/` — refinement audit metadata

## Why no raw upstream content here

Consistent with how WordPress core and Gutenberg are handled: the monorepo lists **what** the upstream is and **how** to access it, but doesn't store the raw content. This keeps the repo lightweight and acknowledges that upstream sources can drift (especially the dev-handbook, which is a living web document).

The refined corpus + patches are sufficient to reproduce and audit transformations:

- **Want to know what the corpus looks like?** → `refined/dev-handbook-clean/`
- **Want to know what was changed and why?** → `patches/v1_1a/`
- **Want to verify the refinement is correct?** → re-scrape upstream + re-run `tools/refine/` → diff against `refined/`

**Rule**: corpus is faithful within the refinement intent. The v1.1a patches edit stylistic issues (broken links, language markers, list formatting) but never alter factual content.
