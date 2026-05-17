# v3.5.12 Carousel #34 — Phase 0 Report

> **Cycle**: v3.5.12  
> **Component**: Carousel #34  
> **Category**: Component Full-Spec + Interaction  
> **Matrix status at entry**: PARTIAL  
> **Phase**: 0 — ontology / inventory / scope lock  
> **Status**: Approved-ready draft  
> **Baseline policy**: documentation-only; no baseline edits in Phase 0

---

## §0. Phase 0 Framing

Carousel is the final Wave 1 entry. It is different from List #33 because it is
already a PARTIAL extracted module from v3.3.2:

```txt
products/reference-implementations/axismundi-lab/modules/carousel/
  lab-carousel.css
  lab-carousel.js
  lab-carousel-pattern.html
  docs/CAROUSEL-AUDIT.md
  docs/CAROUSEL-ONTOLOGY-CHECK.md
  docs/CAROUSEL-VISUAL-QA.md
```

The central Phase 0 question was whether Carousel should follow the ordinary
3-doc Component Full-Spec shape or the 4-doc dual-category shape used when a
component owns an extracted runtime.

**Decision**: Carousel requires a **4-doc audit shape**.

```txt
Create in Phase 1:
  CAROUSEL-SPEC-AUDIT.md
  CAROUSEL-MEASUREMENT-AUDIT.md
  CAROUSEL-WP-MAPPING.md
  CAROUSEL-RUNTIME-AUDIT.md
```

Reason: `lab-carousel.js` owns active index state, layout state, slides-per-view
state, dynamic sizing, drag behavior, pointer capture, arrow-key behavior,
generated pagination dots, `aria-current`, `aria-pressed`, and resize handling.
That is extracted interaction runtime, not native/CSS-only behavior.

---

## §1. Authoritative Inputs Read

Local framework and cycle inputs:

```txt
docs/v3.5.12/CAROUSEL-PHASE-0-PLAN.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
products/reference-implementations/axismundi-lab/stylesheets/components.css §30
products/reference-implementations/axismundi-lab/style-guide.html #components-carousel
products/reference-implementations/axismundi-lab/modules/carousel/*
products/reference-implementations/axismundi-lab/modules/carousel/docs/*
```

External M3 inputs:

```txt
https://m3.material.io/components/carousel/specs
https://m3.material.io/components/carousel/guidelines
```

The M3 pages are JavaScript-rendered, but Playwright headless extraction worked
in this environment. Phase 0 used the extracted body text as bounded evidence,
not as a full design-token dump.

---

## §2. Existing Module Inventory

Current lab module:

| File | Role | Phase 0 finding |
| --- | --- | --- |
| `lab-carousel.css` | Extracted Material slider CSS | Contains runtime-oriented transitions and Material slider naming. No explicit reduced-motion media block was found. |
| `lab-carousel.js` | Extracted interaction runtime | Owns state, drag, transforms, generated dots, keyboard arrows, and resize behavior. |
| `lab-carousel-pattern.html` | Lab-only demo page | Loads only the carousel module; still uses `ax-material-slider` historical naming. |
| `CAROUSEL-AUDIT.md` | v3.3.2 extraction record | Identifies reduced-motion as likely blocker and keyboard as partial. |
| `CAROUSEL-ONTOLOGY-CHECK.md` | WordPress binding ontology | Locks "Gallery is not Carousel" and conditional binding only. |
| `CAROUSEL-VISUAL-QA.md` | Manual QA checklist | Expects Home/End and reduced-motion checks; records reduced-motion as known blocker. |

Key runtime selectors and attributes:

```txt
[data-material-slider]              runtime attach root
.ax-material-slider                 interactive viewport
.ax-material-slider__track          transform target
.ax-material-slide                  dynamic-size item
.ax-material-slider-demo__dot       generated pagination dot
.ax-material-slider-demo__choice    layout / SPV controls
```

Key runtime behavior:

```txt
ArrowLeft / ArrowRight              implemented
Home / End                          not found
pointerdown / pointermove / pointerup / pointercancel implemented
setPointerCapture / releasePointerCapture implemented
aria-current on generated dots      implemented
aria-pressed on demo controls       implemented
resize listener                     implemented
prefers-reduced-motion JS handling  not found
```

---

## §3. Baseline And Style-Guide Inventory

Baseline `components.css` has a static Carousel section:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css §30

.ax-carousel
.ax-carousel__track
.ax-carousel__item
.ax-carousel__item--large
.ax-carousel__item--medium
.ax-carousel__item--small
.ax-carousel__item-media
.ax-carousel__item-label
.ax-carousel--hero
.ax-carousel--uncontained
.ax-carousel__nav
```

The static baseline already includes:

```txt
multi-browse-like structure
hero layout
uncontained layout
scroll-snap / horizontal overflow
small / medium / large item roles
minimal reduced-motion rule for .ax-carousel__track scroll behavior
```

Style guide `#components-carousel` includes:

```txt
standard multi-browse specimen
hero specimen
uncontained specimen
static markup snippets
```

Baseline and style-guide are not edited in Phase 0. The current static baseline
does not replace the extracted lab runtime; it is a lower-risk public surface
that still needs Full-Spec audit alignment.

---

## §4. M3 Carousel Digest

Playwright extraction from M3 specs and guidelines produced enough evidence for
Phase 0 framing.

M3 high-level definition:

```txt
Carousels show a collection of items that can be scrolled on and off the screen.
Items adapt dynamically based on window size.
```

M3 layouts:

```txt
Multi-browse
Uncontained
Hero
Full-screen
Center-aligned hero as a centered hero form
Uncontained multi-aspect ratio as an uncontained form
```

M3 scrolling modes:

```txt
Default scrolling       recommended for uncontained layouts
Snap-scrolling          recommended for multi-browse, hero, and full-screen
```

M3 measurement highlights extracted:

| M3 item | Extracted value |
| --- | --- |
| Multi-browse leading/trailing padding | 16dp |
| Multi-browse top/bottom padding | 8dp |
| Multi-browse between elements | 8dp |
| Multi-browse small item width | 40-56dp dynamic |
| Uncontained leading padding | 16dp |
| Uncontained top/bottom padding | 8dp |
| Uncontained between elements | 8dp |
| Hero leading/trailing padding | 16dp |
| Hero top/bottom padding | 8dp |
| Item corner radius | 28dp |

M3 accessibility guidance extracted:

```txt
On vertically-scrolling pages, carousels require an accessible way to view all
items without horizontal scrolling. Material recommends a Show all button below
the carousel, or an arrow icon button if the carousel has a header.
```

Phase 1 must turn this digest into a formal token/measurement table. Phase 0
does not claim complete M3 token coverage.

---

## §5. Audit Shape Decision

### Decision

Carousel uses the **4-doc dual-category shape**:

```txt
CAROUSEL-SPEC-AUDIT.md
CAROUSEL-MEASUREMENT-AUDIT.md
CAROUSEL-WP-MAPPING.md
CAROUSEL-RUNTIME-AUDIT.md
```

### Why not 3 docs?

The 3-doc pattern is correct when interaction is native/CSS-only, as in Text
field v3.5.7 and Button group v3.5.10. Carousel has an extracted JavaScript
runtime that owns state and behavior:

```txt
active item index
layout mode
slides per view
centered mode
dynamic item sizing
track transform
pointer drag
keyboard arrows
pagination dot generation
ARIA state mutation
resize recalculation
```

This belongs in a runtime audit. Hiding the runtime inside SPEC would make
G11-G16 too vague.

### Historical docs disposition

Existing v3.3.2 docs remain as reference/historical evidence:

```txt
CAROUSEL-AUDIT.md             keep, do not overwrite in Phase 1
CAROUSEL-ONTOLOGY-CHECK.md    keep, cross-reference from WP-MAPPING
CAROUSEL-VISUAL-QA.md         keep, cross-reference from RUNTIME-AUDIT / QA
```

Phase 1 creates the v3.5.12 audit docs alongside them.

---

## §6. Runtime Ownership Decision

`lab-carousel.js` is a component-owned interaction runtime for v3.5.12 purposes.

It is not merely a demo knob layer because the visual carousel behavior depends
on it for:

```txt
slide index changes
transform placement
drag gestures
responsive recomputation
pagination state
keyboard movement
```

However, the current implementation is still lab-scoped:

```txt
Loaded by lab-carousel-pattern.html only
No public WordPress binding
No components.css integration
No interaction bundle promotion
```

Phase 1 RUNTIME-AUDIT must define the stable contract before Phase 2 edits.
Phase 2 may rewrite/refine `lab-carousel.js`, but it must not promote the
runtime to a theme-wide public bundle unless a later release explicitly scopes
that work.

---

## §7. Reduced-Motion Decision

Reduced motion is a **Phase 2 blocker** for Carousel closure.

Evidence:

```txt
Existing CAROUSEL-AUDIT.md criterion 3:
  prefers-reduced-motion was NOT VERIFIED and likely blocker.

Existing CAROUSEL-VISUAL-QA.md:
  reduced-motion failures are known blockers.

components.css §30:
  has a reduced-motion rule for static .ax-carousel__track scroll behavior.

lab-carousel.css / lab-carousel.js:
  no explicit reduced-motion media block or matchMedia handling found.
```

Decision:

```txt
Phase 1 RUNTIME-AUDIT must specify reduced-motion behavior.
Phase 2 must implement reduced-motion support in the lab runtime/CSS before
Phase 3 can pass.
```

Minimum expectation:

```txt
CSS transitions/animations disabled or duration collapsed under reduce.
JS transform movement should avoid animated/drag inertia behavior under reduce.
Scroll behavior should be instant when reduced motion is requested.
```

This does not require mutating `components.css §30` unless Phase 1 discovers a
static baseline mismatch.

---

## §8. Keyboard And Accessibility Decision

Current keyboard support:

```txt
ArrowLeft   implemented
ArrowRight  implemented
Home        not found
End         not found
```

Current accessibility state support:

```txt
viewport role="region" + tabindex="0" in pattern HTML
generated dots receive aria-current
layout/SPV controls receive aria-pressed
```

Decision:

```txt
Phase 1 RUNTIME-AUDIT must define keyboard requirements.
Phase 2 should add Home/End if the v3.5.12 RUNTIME-AUDIT keeps the existing
CAROUSEL-VISUAL-QA expectation.
```

The "Show all" accessibility path from M3 guidelines must be represented in
SPEC/WP-MAPPING as a pattern recommendation. It does not need to be implemented
as a WordPress editor UI in v3.5.12.

G14 / forbidden ancestor:

```txt
If carousel runtime remains lab-only, no global forbidden-ancestor guard is
required in Phase 2.

If Phase 1 proposes any public runtime attach behavior, RUNTIME-AUDIT must
define a guard for editor/prose/forbidden ancestors before promotion.
```

---

## §9. Variant And Pattern Scope

Phase 1 must audit, and Phase 2 should demonstrate, at least:

```txt
multi-browse
hero
uncontained
```

Additional M3 variants to evaluate:

```txt
center-aligned hero
uncontained multi-aspect ratio
full-screen
```

Phase 0 decision:

```txt
Full-screen is likely a deferred/integration variant unless Phase 1 finds a
low-risk lab-only specimen path. It changes viewport/page behavior and should
not be forced into the baseline Carousel primitive.
```

Current naming issue:

```txt
Static baseline uses .ax-carousel.
Extracted runtime uses .ax-material-slider plus .ax-carousel.
```

Phase 1 must decide the canonical naming strategy. Initial recommendation:

```txt
Keep .ax-carousel as the canonical component primitive.
Treat .ax-material-slider as historical lab-runtime adapter naming.
Phase 2 may retain adapter selectors if rewriting them would increase risk,
but new audit docs should frame the component as Carousel, not Material slider.
```

---

## §10. WordPress Mapping Decision

Existing ontology is binding:

```txt
Gallery is not Carousel.
```

Decision:

```txt
No direct core/gallery -> Carousel binding.
```

Allowed conditional mapping:

```txt
core/gallery
  + explicit horizontal/carousel style or pattern
  + valid media-only / short-caption content
  + progressive carousel interaction layer present
  -> Carousel candidate
```

Theme-can:

```txt
static carousel visual pattern
horizontal scroll-snap fallback
conditional style/pattern rendering
reduced-motion runtime behavior
Show all / view-all affordance surface
```

Plugin-should:

```txt
block editor carousel picker UI
custom slide schema
slide reorder UI
remote/federated media logic
carousel custom post type
analytics / autoplay scheduling
```

WP-MAPPING in Phase 1 must preserve this boundary and cross-reference the
existing ontology check.

---

## §11. Dependency Profile

| Dependency | State | Reason |
| --- | --- | --- |
| `components.css §30` | CURRENT static baseline | Public static carousel primitive exists. |
| `lab-carousel.css/js/html` | CURRENT lab runtime source | Existing v3.3.2 extraction is the runtime evidence. |
| `icon-system/` | CURRENT conditional | Navigation controls should use Material Symbols if Phase 2 updates controls; inline SVG is historical. |
| `ripple/` | NONE for track/items; conditional for composed controls | Carousel items are media surfaces, not action targets. Prev/next controls may compose icon-button/ripple. |
| `state-layer §0` | CURRENT for controls only | Demo choices and nav controls use state feedback; media items do not become generic state-layer hosts. |
| `popover/` | NONE | Carousel is not an anchored popup surface. |
| `gallery / media` | DISTINCT but COUPLED | Gallery content may feed a carousel only under explicit conditions. |

Phase 2 note:

```txt
If nav controls are updated, prefer Material Symbols spans over inline SVG for
lab-pattern consistency with Button/Icon button/FAB cycles.
```

---

## §12. Risks And Dispositions

### Risk 1 — 4-doc complexity

Disposition: accepted. Carousel owns extracted runtime; 4-doc shape is the
honest documentation boundary.

### Risk 2 — Reduced-motion blocker

Disposition: Phase 2 blocker. RUNTIME-AUDIT must define behavior; Phase 2 must
implement it before Phase 3.

### Risk 3 — Keyboard gap

Disposition: Arrow keys exist; Home/End not found. Phase 1 decides exact
contract; Phase 2 implements if retained.

### Risk 4 — Naming drift

Disposition: `.ax-carousel` is canonical; `.ax-material-slider` is historical
adapter naming. Phase 1/2 decide whether to retain or reduce adapter naming.

### Risk 5 — Gallery ontology drift

Disposition: direct gallery binding rejected. Conditional binding only.

### Risk 6 — Remote image nondeterminism

Disposition: Phase 2 should prefer local/stable placeholder media or at least
avoid letting remote image load variability block geometry QA.

### Risk 7 — Inline SVG control drift

Disposition: Phase 2 should consider Material Symbols for carousel controls,
lab-scoped only.

### Risk 8 — Runtime promotion creep

Disposition: v3.5.12 may close lab runtime quality. Public interaction-bundle
promotion is out of scope unless Phase 1 explicitly narrows a safe path.

---

## §13. Phase 1 Entry Conditions

Phase 1 may begin after this report is approved.

Create:

```txt
products/reference-implementations/axismundi-lab/modules/carousel/docs/
  CAROUSEL-SPEC-AUDIT.md
  CAROUSEL-MEASUREMENT-AUDIT.md
  CAROUSEL-WP-MAPPING.md
  CAROUSEL-RUNTIME-AUDIT.md
```

Phase 1 requirements:

```txt
1. Convert the M3 digest into formal variant/measurement coverage.
2. Define the runtime contract in RUNTIME-AUDIT.
3. Define reduced-motion requirements.
4. Define keyboard requirements, including Home/End disposition.
5. Preserve "Gallery is not Carousel" in WP-MAPPING.
6. Decide canonical naming / adapter naming boundaries.
7. Define Phase 2 file scope for lab-carousel.css/js/pattern.html.
```

Do not create new lab artifacts in Phase 1 beyond audit docs. Existing
`lab-carousel.*` files remain untouched until Phase 2.

---

## §14. G1-G16 Applicability

Carousel is Component Full-Spec + Interaction.

Applicable:

```txt
G1-G5   Universal / validator / baseline preservation / artifact presence
G6-G10  Component Full-Spec gates
G11-G16 Interaction Runtime gates
```

Key gate implications:

```txt
G6  Static Visual QA must include multi-browse / hero / uncontained and runtime states.
G7  Principle 1 applies: visible controls must be real runtime controls.
G8  Principle 2 applies: native semantics and ARIA state must be accurate.
G11 Runtime hard rules must be written in RUNTIME-AUDIT.
G12 Runtime hard rules must be verified in lab-carousel.js.
G13 Runtime inventory accuracy must be traceable to Phase 0/1.
G14 Forbidden ancestor is required only if runtime attach scope becomes public.
G15 Reduced-motion is applicable and currently a blocker.
G16 Runtime audit document is required.
```

Not applicable:

```txt
G17-G21 Record gates
G22-G26 Infrastructure gates
```

---

## §15. Non-Goals

This Phase 0 does not:

```txt
edit components.css
edit tokens.css
edit style-guide.html
edit theme.json
edit blocks.css
edit lab-carousel.css
edit lab-carousel.js
edit lab-carousel-pattern.html
create WordPress editor UI
create a custom carousel block
create a carousel custom post type
make every core/gallery instance a carousel
solve BACKLOG #32 button-family sizes
solve BACKLOG #33 List token coverage
publish lab preview routes
promote carousel runtime to a public interaction bundle
```

---

## §16. Verdict

Phase 0 verdict:

```txt
APPROVE Phase 1 entry.
```

Locked decisions:

```txt
1. Carousel #34 uses 4-doc shape.
2. lab-carousel.js is component-owned extracted runtime evidence.
3. Reduced motion is a Phase 2 blocker.
4. Arrow keys exist; Home/End gap must be handled in Phase 1/2.
5. .ax-carousel is canonical; .ax-material-slider is historical adapter naming.
6. Gallery is not Carousel; conditional binding only.
7. Ripple is NONE for track/items and conditional through composed controls.
8. Existing v3.3.2 docs remain as historical/reference docs.
```

Next action:

```txt
Create the Carousel Phase 1 4-doc audit set:
  CAROUSEL-SPEC-AUDIT.md
  CAROUSEL-MEASUREMENT-AUDIT.md
  CAROUSEL-WP-MAPPING.md
  CAROUSEL-RUNTIME-AUDIT.md
```

