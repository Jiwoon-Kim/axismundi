# v3.5.12 Carousel #34 — Phase 0 Plan

> **Status**: Phase 0 plan-first document. Await review before Phase 0 report.  
> **Release target**: v3.5.12  
> **Component**: Carousel #34  
> **Matrix status at entry**: PARTIAL (`carousel/` extracted at v3.3.2; Full-Spec layer owed)  
> **Scope**: documentation-only plan; no baseline or lab artifact edits.

---

## §0 — Framing

Carousel #34 is the final Wave 1 entry. Unlike List #33, Carousel is already a
PARTIAL lab module with CSS, JS, pattern HTML, and v3.3.2-era audit docs.
v3.5.12 must decide whether the existing module can be promoted into the
v3.5.x Component Full-Spec framework, and whether its interaction layer
requires a fourth runtime audit doc.

The central Phase 0 question:

```txt
Does Carousel require a 4-doc audit shape because it owns extracted JS runtime,
or can the existing JS remain demo-only enhancement with a 3-doc Full-Spec
trio?
```

The report must answer from local inventory, not analogy alone.

---

## §1 — Current Known State

Existing module:

```txt
products/reference-implementations/axismundi-lab/modules/carousel/
  lab-carousel.css
  lab-carousel.js
  lab-carousel-pattern.html
  docs/CAROUSEL-AUDIT.md
  docs/CAROUSEL-ONTOLOGY-CHECK.md
  docs/CAROUSEL-VISUAL-QA.md
```

Matrix row #34:

```txt
Category: Component Full-Spec + Interaction
Status:   PARTIAL
Existing: carousel/ (v3.3.2)
Target:   carousel/ (Full-Spec layer added)
Wave:     1
Notes:    Carousel interaction extracted v3.3.2; Full-Spec module owed
```

Known v3.3.2 blockers:

```txt
1. prefers-reduced-motion: reduce was NOT VERIFIED in the lab module.
2. Keyboard operability was PARTIAL; Arrow keys existed, Home/End unclear.
3. Existing audit is extraction-era, not v3.5.x Component Full-Spec.
4. WordPress ontology says Gallery is not Carousel; binding must be conditional.
```

---

## §2 — Required Inputs

Phase 0 report must read:

```txt
Core framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
  docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md

Carousel existing local docs:
  products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-ONTOLOGY-CHECK.md
  products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-VISUAL-QA.md

Carousel implementation:
  products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel.css
  products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel.js
  products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel-pattern.html
  products/reference-implementations/axismundi-lab/stylesheets/components.css §30 Carousel
  products/reference-implementations/axismundi-lab/style-guide.html #components-carousel

External M3 sources:
  https://m3.material.io/components/carousel/specs
  https://m3.material.io/components/carousel/guidelines
```

M3 pages are JavaScript-rendered. If plain fetch returns only
`This website requires JavaScript`, Phase 0 must use Playwright extraction and
record that fallback explicitly.

---

## §3 — Lock Decision 1: Audit Doc Shape

Phase 0 must decide:

```txt
Option A — 3-doc trio
  CAROUSEL-SPEC-AUDIT.md
  CAROUSEL-MEASUREMENT-AUDIT.md
  CAROUSEL-WP-MAPPING.md

Use only if lab-carousel.js is judged demo-only and not a component-owned
runtime contract.

Option B — 4-doc set
  CAROUSEL-SPEC-AUDIT.md
  CAROUSEL-MEASUREMENT-AUDIT.md
  CAROUSEL-WP-MAPPING.md
  CAROUSEL-RUNTIME-AUDIT.md

Use if the extracted JS is necessary for Carousel's interaction contract:
drag, prev/next, dots, layout switching, slides-per-view switching, keyboard
navigation, active index, morph rendering, or reduced-motion behavior.
```

Initial recommendation: **Option B likely**, because Carousel already has
extracted JS runtime and matrix row #34 is `Component Full-Spec + Interaction`.
But Phase 0 must verify whether that JS is canonical component behavior or
only a lab demo.

---

## §4 — Lock Decision 2: Runtime Ownership

Phase 0 must classify `lab-carousel.js`:

```txt
Component-owned runtime:
  Owns active index, keyboard navigation, drag, dots, prev/next, layout
  switching, reduced-motion behavior, and ARIA/focus state.
  -> requires RUNTIME-AUDIT.

Demo-only runtime:
  Only controls lab specimen knobs; canonical Carousel remains CSS scroll-snap.
  -> 3-doc trio may be sufficient, with JS documented as lab-only.
```

Evidence to inspect:

```txt
lab-carousel.js:
  - pointerdown / pointermove / pointerup drag logic
  - keydown ArrowLeft / ArrowRight
  - missing or present Home / End
  - ResizeObserver or resize listener behavior
  - aria-current / aria-pressed / disabled state updates
  - reduced-motion awareness or absence
```

---

## §5 — Lock Decision 3: Reduced Motion

The v3.3.2 audit named reduced motion as the likely blocker. Phase 0 must
separate baseline Carousel from lab Material slider:

```txt
components.css §30:
  already has @media (prefers-reduced-motion: reduce) for the static
  .ax-carousel__track scroll behavior.

lab-carousel.css / lab-carousel.js:
  must be verified for transform, flex-basis, inline-size, opacity, and
  scroll/morph transitions.
```

Phase 0 must decide whether reduced-motion remediation is:

```txt
Phase 2 required:
  if lab runtime is part of v3.5.12 closure.

Documentation-only:
  if lab Material slider remains demo-only and static Carousel is the
  canonical Full-Spec surface.
```

Initial recommendation: treat reduced motion as **Phase 2 required** if
Option B 4-doc runtime shape is chosen.

---

## §6 — Lock Decision 4: M3 Variant Scope

Phase 0 must inventory M3 Carousel variants and compare with baseline/lab:

```txt
Variants to verify:
  - Multi-browse
  - Hero
  - Uncontained
  - Full-screen? (verify from current M3 spec)
  - Center-aligned / compact controls? (verify from current M3 spec)
```

Existing local surfaces:

```txt
components.css §30:
  .ax-carousel
  .ax-carousel--hero
  .ax-carousel--uncontained
  .ax-carousel__track
  .ax-carousel__item--large / --medium / --small

lab module:
  .ax-material-slider
  .ax-material-slide
  layout controls: multi / hero / uncontained
  slidesPerView: 1..5
  centeredSlides checkbox
```

Phase 0 must decide whether `ax-material-slider` is a runtime-enhanced variant
of `.ax-carousel` or a historical benchmark specimen that should be rewritten
into canonical `.ax-carousel` terms during Phase 2.

---

## §7 — Lock Decision 5: WordPress Binding

Existing ontology: **Gallery is not Carousel.**

Phase 0 must preserve the v3.3.2 binding rule:

```txt
core/gallery
  + explicit horizontal/carousel style or pattern
  + progressive interaction layer present
  -> Carousel candidate

core/gallery alone
  -> remains Gallery, not Carousel
```

Phase 1 WP-MAPPING should formalize:

```txt
Theme territory:
  - visual carousel pattern
  - CSS scroll-snap fallback
  - optional prev/next runtime if progressive enhancement stays accessible

Plugin territory:
  - editor carousel picker UI
  - custom slide schema
  - slide reorder UI
  - remote/federated media logic
  - carousel custom post type
```

---

## §8 — Lock Decision 6: Dependency Profile

Expected dependency state:

```txt
ripple/:
  likely NONE for the carousel track/items themselves;
  possible TARGET via composed icon-button prev/next controls only.

icon-system/:
  CURRENT conditional for prev/next controls if Material Symbols are used.
  Existing pattern still has inline SVG chevrons; Phase 0 must decide whether
  v3.5.12 converts them or leaves icon conversion to a separate sweep.

components.css §0 state-layer:
  CURRENT for composed controls and demo chips, not for slide surface itself.

popover/:
  NONE.

Avatar/List/Card/etc.:
  composition only if future content specimens use them; not owned by Carousel.
```

---

## §9 — Lock Decision 7: Baseline Edit Scope

Phase 0 must decide whether v3.5.12 will edit baseline:

```txt
Allowed only if:
  - mismatch is inside components.css §30 Carousel,
  - M3 spec or accessibility requires it,
  - Playwright can verify no Wave 1 regression,
  - fallback trigger routes wider changes to BACKLOG.

Default recommendation:
  Phase 0/1 documentation first.
  Phase 2 may edit lab-carousel.* and existing carousel docs.
  Baseline §30 edits only if Phase 0 finds a narrow blocker, especially
  reduced-motion or class-name precision.
```

Do not edit:

```txt
tokens.css
components.css sections outside §30
style-guide.html outside #components-carousel
blocks.css
theme.json
closed Wave 1 component modules
```

---

## §10 — Phase 0 Report Shape

`docs/v3.5.12/CAROUSEL-PHASE-0-REPORT.md` should include:

```txt
§0  Framing and release target
§1  Authoritative inputs read
§2  Existing module inventory (CSS / JS / pattern / docs)
§3  Baseline §30 + style-guide specimen inventory
§4  M3 Carousel spec/guideline digest via Playwright extraction
§5  Audit shape decision: 3-doc vs 4-doc
§6  Runtime ownership decision
§7  Reduced-motion blocker analysis
§8  Keyboard/accessibility analysis
§9  Variant matrix and local coverage
§10 WordPress conditional binding decision
§11 Dependency profile
§12 Risks and dispositions
§13 Phase 1 entry conditions
§14 G1-G16 gate applicability
§15 Non-goals
§16 Verdict
```

---

## §11 — Expected Risks

| Risk | Expected disposition |
|---|---|
| 3-doc vs 4-doc drift | Phase 0 decides from runtime ownership; no automatic analogy |
| Reduced-motion gap | Must be verified; likely Phase 2 fix if runtime is canonical |
| Keyboard gap | Arrow keys exist; Home/End unclear; Phase 0 must classify severity |
| M3 JS-rendered docs | Use Playwright extraction; record fallback |
| Gallery direct-binding temptation | Reject; preserve conditional binding |
| Inline SVG prev/next icons | Decide whether to convert to Material Symbols or route to future icon sweep |
| `ax-material-slider` naming drift | Decide whether historical naming remains acceptable or canonicalize toward `.ax-carousel` in Phase 2 |
| Remote image dependency | Pattern HTML uses `picsum.photos`; Phase 0 should decide if v3.5.12 needs local/gradient fallback for deterministic QA |

---

## §12 — Non-Goals

v3.5.12 Phase 0 does not:

- edit `lab-carousel.css`,
- edit `lab-carousel.js`,
- edit `lab-carousel-pattern.html`,
- edit `components.css`,
- edit `style-guide.html`,
- edit `tokens.css`,
- edit WordPress binding JSON,
- register block styles,
- create a carousel editor UI,
- create slide schema,
- implement remote/federated media logic,
- close BACKLOG #32 or #33,
- touch closed Wave 1 modules,
- update CURRENT-STATE.md or NEXT-SESSION.md.

---

## §13 — Validation

Phase 0 plan validation:

```txt
Expected changed file:
  docs/v3.5.12/CAROUSEL-PHASE-0-PLAN.md

Expected unchanged:
  components.css
  tokens.css
  style-guide.html
  blocks.css
  theme.json
  lab/modules/carousel/*
  CURRENT-STATE.md
  NEXT-SESSION.md
```

Validator must remain:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

---

## §14 — Self-Check Summary

```txt
self-check:
  3-doc vs 4-doc decision mentions: present
  reduced-motion blocker mentions: present
  keyboard/Home/End risk mentions: present
  Gallery is not Carousel binding: present
  M3 Playwright extraction fallback: present
  baseline edit scope lock: present
  CURRENT-STATE/NEXT-SESSION untouched: present
```
