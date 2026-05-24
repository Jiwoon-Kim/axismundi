# Axismundi License Matrix

> Per-surface license declarations for the public repository. The root code
> license is finalized as **GPL-3.0-or-later** for WordPress.org compatibility.
> Documentation and ontology/data surfaces use **CC BY-SA 4.0** unless a more
> specific upstream license applies.

**Primary code license**: GPL-3.0-or-later  
**Documentation license**: CC BY-SA 4.0  
**Ontology / data license**: CC BY-SA 4.0  
**Public release status**: v3.5.14 publish-prep aligned; GitHub repo creation remains v3.5.15

---

## 0. Code vs Content Boundary

| Surface | Examples | License |
|---|---|---|
| Theme / runtime code | CSS, JS, Python tools, generated theme/runtime code, HTML templates intended as executable/rendered theme assets | GPL-3.0-or-later |
| Documentation | README files, audit docs, framework docs, CHANGELOG, ROADMAP, governance docs | CC BY-SA 4.0 |
| Ontology / data | `core/`, `bindings/`, status matrices, binding maps, schema/data projections | CC BY-SA 4.0 |
| WordPress-derived corpus | `corpus/refined/dev-handbook-clean/` and related WordPress documentation derivatives | Original WordPress documentation terms apply; see section 2 |
| Third-party assets | Fonts, icons, upstream material preserved in asset folders | Original upstream license preserved |
| Project media placeholders | `assets/media/` image/audio/video placeholders | Per-file license in `assets/LICENSES.md` |
| Brand identity assets | `assets/brand/` project symbol variants | Project-owned brand assets; see `assets/LICENSES.md` |

When a file mixes code and explanatory prose, the code portions follow the code
license and the surrounding documentation follows the documentation license
unless a file-level notice says otherwise.

---

## 1. WordPress Block Theme / Runtime Code

| Field | Value |
|---|---|
| Current source path | `products/reference-implementations/axismundi-lab/` |
| Future distributable path | `products/distributables/themes/axismundi/` |
| License | GPL-3.0-or-later |
| Root license file | `LICENSE` |
| Rationale | GPL-compatible WordPress theme direction; compatible with Apache-2.0 Material Symbols; aligned with WordPress.org theme expectations |

---

## 2. WordPress-Related Refined Corpus

| Field | Value |
|---|---|
| Path | `corpus/refined/dev-handbook-clean/` |
| Source | WordPress Developer Documentation |
| Content license | Original source terms apply; CC0 where applicable |
| Code snippets | GPL-2.0-or-later where inherited from WordPress examples |
| Reference | https://make.wordpress.org/docs/licensing/ |
| Status | Refinement patches are mechanical transformations; original content licensing is preserved |

---

## 3. Tools / Scripts

| Field | Value |
|---|---|
| Path | `tools/` |
| License | GPL-3.0-or-later |
| Rationale | Simpler public distribution model for v3.5.14; scripts operate as part of the theme/tooling repo |

---

## 4. Ontology / Bindings

| Field | Value |
|---|---|
| Path | `core/`, `bindings/`, status matrices, binding maps |
| License | CC BY-SA 4.0 |
| License file | `LICENSE-CC-BY-SA-4.0.md` |
| Rationale | Knowledge/data structures are not ordinary executable code; share-alike preserves reciprocal improvements |

---

## 5. Doctrine / Governance Docs

| Field | Value |
|---|---|
| Path | `CONSTITUTION.md`, `ROADMAP.md`, `CHANGELOG.md`, project docs, audit docs, layer READMEs |
| License | CC BY-SA 4.0 |
| License file | `LICENSE-CC-BY-SA-4.0.md` |
| Rationale | Public developer documentation should remain reusable with attribution and reciprocal sharing |

---

## 6. Material Symbols

| Field | Value |
|---|---|
| Path | `core/design-systems/material3/assets/icons/` |
| Subdirectories | `material-symbols-outlined/`, `material-symbols-rounded/`, `material-symbols-sharp/` |
| License | Apache License 2.0 |
| Source | https://fonts.google.com/icons |
| GitHub | https://github.com/google/material-design-icons |
| Files included | `material-symbols-{style}.woff2` |
| Modification | Format conversion to WOFF2 where applicable; axes and glyph table preserved |
| Attribution | `NOTICE.md` |
| License files | `LICENSE.txt` preserved in each subdirectory |

### Apache 2.0 Compatibility

Apache 2.0 is compatible with GPL-3.0-or-later in the direction needed here:
Apache-licensed assets/code can be combined into a GPL-3.0-or-later project.
This is the reason Axismundi uses GPL-3.0-or-later rather than GPL-2.0-only for
the theme/runtime code surface.

---

## 7. Fonts

All shipped web fonts are sourced from Google Fonts and distributed under SIL
Open Font License 1.1. Each font subdirectory includes a verbatim `OFL.txt`.

| Font | Path | Source | License | Conversion |
|---|---|---|---|---|
| Roboto Flex | `core/design-systems/material3/assets/fonts/roboto-flex/` | https://github.com/google/fonts/tree/main/ofl/robotoflex | OFL 1.1 | WOFF2 full coverage |
| Roboto Serif | `core/design-systems/material3/assets/fonts/roboto-serif/` | https://github.com/google/fonts/tree/main/ofl/robotoserif | OFL 1.1 | WOFF2 |
| Roboto Mono | `core/design-systems/material3/assets/fonts/roboto-mono/` | https://github.com/google/fonts/tree/main/ofl/robotomono | OFL 1.1 | WOFF2 full coverage |
| Noto Sans KR | `core/design-systems/material3/assets/fonts/noto-sans-kr/` | https://github.com/google/fonts/tree/main/ofl/notosanskr | OFL 1.1 | WOFF2 + Korean subset |
| Noto Serif KR | `core/design-systems/material3/assets/fonts/noto-serif-kr/` | https://github.com/google/fonts/tree/main/ofl/notoserifkr | OFL 1.1 | WOFF2 + Korean subset |

### OFL 1.1 Compliance Notes

- Format conversion and unicode-range subsetting are permitted modifications
  under OFL 1.1.
- Reserved Font Names are not altered in CSS family names.
- `OFL.txt` is preserved in each font subdirectory.
- Fonts are not sold separately.

---

## 8. Beer CSS Reference

| Field | Value |
|---|---|
| Influence | Historical inspiration for early interaction experiments: ripple, carousel, popover |
| Original license | MIT |
| Attribution | `NOTICE.md` |
| Current status | v3.5.x runtime contracts are Material 3-grounded and audited in their module docs. A future provenance sweep may compare early archived experiments line-by-line, but public v3.5.x surfaces should not claim Beer CSS authorship or copy source code. |

---

## 9. Project Brand And Placeholder Media

| Field | Value |
|---|---|
| Path | `assets/brand/`, `assets/media/` |
| License file | `assets/LICENSES.md` |
| Brand assets | Original project identity assets by Jiwoon Kim; reserved for Axismundi project/theme/plugin identity use |
| Image placeholder | WordPress Photo Directory CC0 photo by Jiwoon Kim |
| Audio placeholder | Project-author supplied AI-generated Suno demo audio; MP3 source/reference plus Opus derivative included |
| Video placeholder | Pixabay Content License video by Evgeniy__Mironov; not project-owned |
| Status | Placeholder assets for block catalog / theme template work; not all assets share one license |

---

## 10. WordPress.org Submission Compatibility

WordPress.org theme submission expects the theme package contents to be GPL or
GPL-compatible.

| Check | Status |
|---|---|
| Theme/runtime code | GPL-3.0-or-later |
| Material Symbols | Apache-2.0, GPL-3-compatible direction, attributed in NOTICE |
| Fonts | OFL 1.1, per-folder OFL.txt preserved |
| Documentation | CC BY-SA 4.0, outside the eventual theme zip unless intentionally bundled |
| Ontology/data | CC BY-SA 4.0, outside the eventual theme zip unless intentionally bundled |
| WordPress-derived corpus | Original terms identified separately |
| Google / Material trademarks | Attribution only; no trademark endorsement claim |
| `readme.txt`, screenshot, theme tags | Deferred to later WordPress.org submission prep |

---

## Aggregate

Public repository license matrix:

- Code / theme / tooling: GPL-3.0-or-later
- Documentation: CC BY-SA 4.0
- Ontology / data: CC BY-SA 4.0
- Material Symbols: Apache 2.0
- Fonts: OFL 1.1
- WordPress-derived corpus: original WordPress documentation terms
- Beer CSS: MIT attribution for historical inspiration

For attribution details, see `NOTICE.md`.
