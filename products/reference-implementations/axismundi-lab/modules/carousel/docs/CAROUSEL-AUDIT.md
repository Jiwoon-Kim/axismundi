# Carousel Audit — v3.3.2


> **v3.4.8 Deletion Notice (added retrospectively):**
> The benchmark source files referenced throughout this document
> (`scripts/benchmark-interactions.js`, `stylesheets/benchmark-interactions.css`,
> and `style-guide-benchmark.html`) were **removed from the active tree at
> v3.4.8 Benchmark Surface Deletion**. Line ranges and quoted file paths in
> this document are HISTORICAL references — they describe where the source
> code lived during extraction, not where it lives now. Provenance is
> preserved in this audit document, CHANGELOG.md / ROADMAP.md, the
> v3.X.Y zip freezes, and git history.

> Module extraction record + ongoing audit for the Material You morph carousel
> lab module. Follows the precedent set by `INTERACTION-AUDIT.md` (v3.2.2) but
> scoped to a single module instead of the full benchmark layer.

## Extraction record (v3.3.2)

The Material You morph slider/carousel code was moved out of the shared
benchmark surface into a self-contained lab module so the remaining benchmark
items can be processed independently in later versions.

### Source locations (before extraction)

| Asset | Path | Range |
|---|---|---|
| CSS | `stylesheets/benchmark-interactions.css` | L271–520 (`Material You morph slider benchmark` section) |
| JS  | `scripts/benchmark-interactions.js`     | L9–10 (`qs`/`qsa`), L12–18 (`onReady`), L458–460 (`clamp`), L462–749 (`enableMaterialYouSliders` + nested helpers) |
| HTML demo | `style-guide-benchmark.html`         | L1416–1648 (`<section id="components-carousel">`) |

### Target locations (after extraction)

| Asset | Path |
|---|---|
| CSS | `stylesheets/lab-carousel.css` |
| JS  | `scripts/lab-carousel.js` (self-contained IIFE with copied utilities + own bootstrap) |
| HTML demo | `lab-carousel-pattern.html` (lab-internal demo page) |
| Audit | `docs/CAROUSEL-AUDIT.md` (this file) |
| Ontology | `docs/CAROUSEL-ONTOLOGY-CHECK.md` |
| Visual QA | `docs/CAROUSEL-VISUAL-QA.md` |

### Audit-trail treatment of original sections

Per the benchmark promotion policy (do not delete on extraction):

- The original CSS block in `benchmark-interactions.css` is preceded by a
  `/* EXTRACTED: v3.3.2 → stylesheets/lab-carousel.css */` marker comment.
- The original JS functions in `benchmark-interactions.js` are preceded by an
  equivalent `// EXTRACTED: v3.3.2 → scripts/lab-carousel.js` marker comment.
- The headers of both benchmark files were updated with a `v3.3.2 update`
  section that records what was extracted and lists what remains in the queue
  for v3.3.3+ extraction.
- The benchmark page (`style-guide-benchmark.html`) is unchanged — its
  carousel demo continues to render via `benchmark-interactions.{css,js}`,
  so anyone re-opening the benchmark page sees the same thing as before.

Cleanup of the now-duplicated code in benchmark is deferred to a benchmark
prune pass (planned v3.4.x / v3.5.0) that will run after all known reference
patterns have been extracted into their own modules.

## Lineage

Visual pattern reference: `compare/Material You Slider.html` and its asset
folder. That demo and its accompanying screenshots were consulted while the
benchmark Material You section was first authored. By the time the benchmark
code was written, the Axismundi token system (M3 sys-color / sys-shape /
sys-motion families) and BEM-style class conventions were already mature, so
the extracted module is meaningfully refined relative to the original
reference rather than a verbatim port. Key refinements observed during this
audit:

- All literal colors → `var(--md-sys-color-*)` tokens.
- All literal radii → `var(--md-sys-shape-corner-*)` tokens.
- Container queries / responsive sizing in the demo bar uses
  `var(--space-*)` tokens rather than ad-hoc pixel values.
- The Multi / Hero / Uncontained variants are wired through `.is-layout-*`
  modifier classes that align with the BEM modifier convention used elsewhere
  in `components.css` (e.g. `.ax-button--filled` is currently being migrated
  to `.ax-button.is-filled` — same family).
- Demo controls (`.ax-material-slider-demo__choice`) compose on top of the
  main `.chip` component from `components.css §11`.
- Drag interaction uses pointer events (not touch / mouse separately) — newer
  pattern than the original reference.

`compare/Material You Slider.html` is preserved as frozen reference (not
authority) under the project's `compare/` workspace policy.

## Promotion criteria — when this module is allowed into `components.css`

This module is currently **lab-internal**. It is not allowed into
`components.css §G3 Carousel` (the static visual-structure version) until it
passes all five lab promotion criteria. These are the same five criteria
defined in `INTERACTION-AUDIT.md` (v3.2.2 methodology section), recorded here
so this audit is self-contained:

1. **Works without JS** — When `lab-carousel.js` fails to load or the
   `enableMaterialYouSliders()` call does not run, the carousel must still
   render as a usable horizontal scroller. The underlying `.ax-material-slider`
   markup relies on native `overflow-x: auto` + `scroll-snap-type` for the
   no-JS path. Status: **PASS by construction** — JS only enhances; the
   scroll-snap CSS works on its own. Needs visual confirmation in QA.

2. **M3 state-layer compliance** — Hover / focus / pressed states on
   `.ax-material-slider-demo__choice` and other interactive elements must use
   `color-mix()` with `--md-sys-state-*-state-layer-opacity` tokens (per the
   §0 State-layer foundation in `components.css`). Status: **inherited from
   `.chip` composition** (`.chip` is already state-layer compliant). Direct
   demo-bar buttons confirmed token-based. Needs explicit verification in
   `lab-carousel.css`.

3. **`prefers-reduced-motion: reduce` honored** — Any transform / scroll
   animation must collapse to an instant snap when reduced-motion is set.
   Status: **NOT YET VERIFIED**. The CSS does not currently include an
   explicit `@media (prefers-reduced-motion: reduce)` block scoped to
   `.ax-material-slider`. This is the most likely blocker — needs a
   reduced-motion rule before promotion.

4. **Keyboard-operable** — Tab to the carousel, Arrow keys advance / retreat,
   Home / End jump to first / last, focus visibly tracks the active item.
   Status: **PARTIAL**. `enableMaterialYouSliders` wires pointer drag and
   resize but the keyboard handler scope needs explicit audit — see
   `CAROUSEL-VISUAL-QA.md §Keyboard`.

5. **No leak into `.prose` / `post_content` / federated surfaces** —
   `.ax-material-slider`, `.ax-material-slide`, and all `.ax-material-slider-demo__*`
   classes must never apply inside `.prose` (long-form content) or
   `.wp-block-post-content`. The selectors as written are non-greedy
   (specific classes, no element selectors), so accidental scope leakage is
   unlikely. Status: **PASS by selector design** — no global element
   selectors used.

### Current verdict

| Criterion | Status | Blocker? |
|---|---|---|
| 1. Works without JS | PASS by construction (needs visual confirm) | No |
| 2. M3 state-layer compliance | PARTIAL (verify on demo-bar buttons) | Low |
| 3. `prefers-reduced-motion` honored | **NOT VERIFIED — needs rule** | **YES** |
| 4. Keyboard-operable | PARTIAL (audit scope unclear) | Medium |
| 5. No `.prose` / federation leak | PASS by selector design | No |

**Recommended next step (post-v3.3.2)**: add a `@media (prefers-reduced-motion:
reduce)` block + a keyboard handler audit in a v3.3.2.x patch or v3.3.3
follow-on. The promotion-to-main decision is held until all five criteria are
green.

## Module file inventory

```
products/reference-implementations/axismundi-lab/
├─ stylesheets/
│  └─ lab-carousel.css                 ← module CSS (extracted)
├─ scripts/
│  └─ lab-carousel.js                  ← module JS (extracted, self-contained IIFE)
├─ lab-carousel-pattern.html           ← module demo page (loads only this module)
└─ docs/
   ├─ CAROUSEL-AUDIT.md                ← this file
   ├─ CAROUSEL-ONTOLOGY-CHECK.md       ← Gallery ≠ Carousel binding map
   └─ CAROUSEL-VISUAL-QA.md            ← visual / interaction QA checklist
```

The `publish_styleguide.py` generator was intentionally not modified in
v3.3.2. It will pick up `lab-carousel.css` via its `*.css` glob and copy it
to `/styleguide/stylesheets/lab-carousel.css`, where it sits as an orphan
(no HTML on the publish surface links to it). `lab-carousel.js` and
`lab-carousel-pattern.html` are NOT copied to the publish surface because
the script only copies the explicitly named `style-guide.js` and the three
canonical style-guide HTML pages. This asymmetry is acceptable for v3.3.2
and will be addressed in v3.4.x Lab Module Restructure (modular folder layout
+ module-aware publish tool).

## Future audit cycles

Subsequent carousel audit revisions will be needed when:

- The reduced-motion rule lands (criterion 3 update).
- The keyboard handler is verified or expanded (criterion 4 update).
- A WordPress `core/gallery` block fallback prototype is built (see
  `CAROUSEL-ONTOLOGY-CHECK.md` for the binding logic).
- Promotion to `components.css` is approved.

Each revision appends a new section to this file (not a new file) so the
audit trail stays single-source.
