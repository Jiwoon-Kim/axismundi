# Carousel — Runtime Audit

> **Status**: v3.5.12 release closed; Phase 3 visual QA PASS.  
> **Component**: Carousel #34  
> **Category**: Interaction Runtime side of Component Full-Spec + Interaction  
> **Companions**: `CAROUSEL-SPEC-AUDIT.md`, `CAROUSEL-MEASUREMENT-AUDIT.md`, `CAROUSEL-WP-MAPPING.md`

---

## §0 — Runtime Audit Status

This document owns the v3.5.12 Carousel runtime contract and G11-G16 gate
readiness. It treats the v3.3.2 `lab-carousel.js` extraction as current runtime
evidence, not as final public API.

---

## §1 — Inputs Read

```txt
docs/v3.5.12/CAROUSEL-PHASE-0-REPORT.md
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel.js
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel.css
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel-pattern.html
products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-AUDIT.md
products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-VISUAL-QA.md
```

---

## §2 — Runtime Category Framing

Carousel is dual-category:

```txt
Component Full-Spec:
  layout variants, item measurements, slots, WordPress mapping.

Interaction Runtime:
  active index, transforms, drag, dots, keyboard movement, reduced motion,
  resize recalculation, ARIA state mutation.
```

The runtime doc exists because those behaviors are not native/CSS-only.

---

## §3 — Current Runtime Inventory

Current runtime facts:

```txt
IIFE module
no public window API
attach root: [data-material-slider]
viewport: .ax-material-slider
track: .ax-material-slider__track
slides: .ax-material-slide
dots generated into .ax-material-slider-demo__dots
prev/next buttons discovered by [data-material-prev] / [data-material-next]
layout controls discovered by [data-material-layout]
slides-per-view controls discovered by [data-material-spv]
centered toggle discovered by [data-material-centered]
```

Current runtime state:

```txt
index
slideSize
slidesPerView
layout
centered
slideSizes
slideOffsets
dragging
dragStartX
dragStartOffset
dragOffset
```

---

## §4 — Contract Surface

Phase 1 contract decision:

```txt
No public window.axCarousel API in v3.5.12.
```

Runtime contract remains declarative/lab-local:

```txt
[data-material-slider] root
required descendants: viewport, track, slides
optional controls: prev/next, layout buttons, SPV buttons, centered toggle, dots container
```

If Phase 2 changes the attach attribute, it must preserve a migration note and
not silently break the existing lab pattern.

---

## §5 — Movement And State Contract

Movement methods:

```txt
prev / next buttons call go(-1) / go(1)
ArrowLeft / ArrowRight call go(-1) / go(1)
pagination dots jump to index
pointer drag computes a delta and snaps to a new index
resize recomputes layout
```

ARIA state:

```txt
dots: aria-current true/false
layout controls: aria-pressed true/false
SPV controls: aria-pressed true/false
```

Phase 2 must verify this state after each interaction.

---

## §6 — Keyboard Contract

Current support:

```txt
ArrowLeft   implemented
ArrowRight  implemented
Home        missing
End         missing
```

v3.5.12 runtime requirement:

```txt
ArrowLeft / ArrowRight move one item.
Home moves to first item.
End moves to last item.
Focus remains on the carousel viewport unless a control explicitly receives focus.
Keyboard users can leave the carousel with Tab / Shift+Tab.
```

Phase 2 should add Home/End support. If not added, Phase 2 must downgrade the
contract explicitly and Phase 3 cannot claim full keyboard parity with the
existing visual QA checklist.

---

## §7 — Pointer / Drag Contract

Current pointer support:

```txt
pointerdown records start position and captures pointer
pointermove updates transform while dragging
pointerup / pointercancel release capture and snap
dragstart is prevented
```

Contract:

```txt
Drag is an enhancement, not the only movement path.
Buttons, dots, and keyboard remain available.
```

WCAG implication:

```txt
SC 2.5.7 Dragging Movements requires a non-drag alternative.
```

---

## §8 — Reduced-Motion Contract

Current status:

```txt
No explicit lab-scoped prefers-reduced-motion CSS rule found.
No matchMedia('(prefers-reduced-motion)') runtime branch found.
```

v3.5.12 requirement:

```txt
Under prefers-reduced-motion: reduce:
  transform, flex-basis, opacity, shape, and label transitions collapse to instant state changes.
  drag enhancement must not introduce kinetic or animated movement.
  scroll/transform movement should avoid animated interpolation.
```

Phase 2 must implement this before Phase 3.

---

## §9 — No-JS Fallback Contract

Existing v3.3.2 audit says no-JS fallback passes by construction because the
markup remains a horizontal scroller.

Runtime requirement:

```txt
Without lab-carousel.js:
  carousel content remains visible
  horizontal scroll remains usable
  no generated dots are required
  no hidden content becomes inaccessible
```

Phase 3 should verify no-JS fallback by disabling script or by inspecting the
static path in a separate fixture.

---

## §10 — DOM Mutation And Idempotence

Current mutation:

```txt
runtime creates pagination dot buttons
runtime mutates classes on viewport/slides/dots/controls
runtime mutates inline transform and slide sizing styles
runtime installs event listeners per [data-material-slider] root
```

Phase 2 requirements:

```txt
setup must be idempotent or safe for one load
generated dots must not duplicate on repeated initialization
destroy API is not required for v3.5.12 unless Phase 2 adds public attachment
```

---

## §11 — Runtime Rules In Code

Phase 2 implementation checklist:

```txt
reduced-motion media/query support present
Home / End present or explicitly deferred
ArrowLeft / ArrowRight preserved
prev/next click preserved
dots click preserved
aria-current updates after go()
aria-pressed updates after layout/SPV changes
pointer drag remains optional
resize recomputation preserved
no page horizontal overflow in QA
```

---

## §12 — Forbidden Ancestor / Attach Scope

Current runtime is lab-only and attaches only inside `lab-carousel-pattern.html`.

Decision:

```txt
No global forbidden-ancestor guard is required while runtime remains lab-only.
```

If Phase 2 proposes public attach behavior, it must add a guard strategy before
promotion:

```txt
do not auto-attach inside prose/post_content/editor surfaces unless explicitly opted in
do not mutate arbitrary core/gallery blocks
```

---

## §13 — G11-G16 Readiness

| Gate | Phase 1 status |
| --- | --- |
| G11 Hard runtime rules | PASS draft; rules defined in this doc. |
| G12 Rules verified in code | PASS at Phase 5. |
| G13 Inventory accuracy | PASS Phase 1; inventory traced to `lab-carousel.js`. |
| G14 Forbidden ancestor | N/A while lab-only; required before public attach. |
| G15 Reduced motion | PASS at Phase 5. |
| G16 Runtime audit doc | PASS; this document exists. |

---

## §14 — Runtime Risks

| Risk | Disposition |
| --- | --- |
| Reduced-motion missing | Phase 2 blocker. |
| Home/End missing | Phase 2 expected fix. |
| Generated dots duplication | Phase 2 idempotence check. |
| Remote image variability | Phase 2/3 should stabilize QA assets if needed. |
| Naming drift | Keep `.ax-carousel` canonical; adapter naming acceptable short-term. |
| Public promotion creep | Out of scope. |

---

## §15 — Phase 2 Runtime Scope

Allowed:

```txt
edit lab-carousel.js
edit lab-carousel.css
edit lab-carousel-pattern.html
```

Expected runtime edits:

```txt
add reduced-motion support
add Home/End support
verify or stabilize dot generation
possibly replace inline SVG controls with Material Symbols in pattern
```

Not allowed without a new plan:

```txt
public window API
global attach outside lab pattern
WordPress binding mutation
components.css runtime promotion
```

---

## §16 — Verdict

Phase 5 runtime verdict:

```txt
ALL APPLICABLE RUNTIME GATES PASS.

G11 hard runtime rules:
  PASS. Runtime contract covers active index, controls, dots, drag,
  keyboard, reduced motion, and no-JS fallback.

G12 rules verified in code:
  PASS. Phase 2 implementation added reduced-motion support, Home/End,
  .is-enhanced fallback separation, and retained Arrow/prev/next/dot behavior.

G13 inventory accuracy:
  PASS. Runtime inventory traces to lab-carousel.js and v3.3.2 extraction docs.

G14 forbidden ancestor:
  N/A while lab-only. Required before any public/global attach release.

G15 reduced motion:
  PASS. CSS transitions/animations collapse under reduce; JS tracks
  matchMedia state.

G16 runtime audit:
  PASS. This RUNTIME-AUDIT owns G11-G16.
```

Phase 2 runtime update:

```txt
Reduced-motion support added:
  lab-carousel.css now disables transitions/animations under reduce.
  lab-carousel.js tracks matchMedia('(prefers-reduced-motion: reduce)') and
  syncs the runtime state class.

Keyboard support completed:
  Home moves to first item.
  End moves to last item.
  ArrowLeft / ArrowRight preserved.

No-JS fallback preserved:
  runtime adds .is-enhanced only after setup.
  without JavaScript, the track remains a native overflow-x scroll-snap rail.

Playwright pre-check:
  active dot / aria-current updates through End, Home, ArrowRight, ArrowLeft.
  reduced-motion emulation reports transition-property none on runtime targets.
```

Phase 3 close:

```txt
User visual QA: PASS.
Reduced-motion blocker: CLOSED.
Home/End blocker: CLOSED.
No-JS fallback: PASS.
```

---

## §17 — Cross-References

```txt
CAROUSEL-SPEC-AUDIT.md
CAROUSEL-MEASUREMENT-AUDIT.md
CAROUSEL-WP-MAPPING.md
CAROUSEL-AUDIT.md
CAROUSEL-VISUAL-QA.md
docs/v3.5.12/CAROUSEL-PHASE-0-REPORT.md
```
