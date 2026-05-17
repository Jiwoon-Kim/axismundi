# Carousel — Spec Audit

> **Status**: v3.5.12 release closed; Phase 3 visual QA PASS.  
> **Component**: Carousel #34  
> **Category**: Component Full-Spec + Interaction  
> **Companions**: `CAROUSEL-MEASUREMENT-AUDIT.md`, `CAROUSEL-WP-MAPPING.md`, `CAROUSEL-RUNTIME-AUDIT.md`  
> **Historical references**: `CAROUSEL-AUDIT.md`, `CAROUSEL-ONTOLOGY-CHECK.md`, `CAROUSEL-VISUAL-QA.md`

---

## §0 — Audit Status

This is the v3.5.12 canonical Carousel SPEC audit. It does not replace the
v3.3.2 extraction record. It translates the existing Carousel module into the
v3.5.x Component Full-Spec vocabulary and delegates runtime behavior to
`CAROUSEL-RUNTIME-AUDIT.md`.

Phase 5 verdict criteria:

| # | Criterion | Status |
| --- | --- | --- |
| 1 | M3 Carousel spec coverage | PASS at Phase 5 |
| 2 | Token-driven implementation | PASS at Phase 5 |
| 3 | Pattern HTML completeness | PASS at Phase 2 |
| 4 | Audit doc completeness | PASS at Phase 1 |
| 5 | Dependency declarations | PASS at Phase 1 |

---

## §1 — Inputs Read

```txt
docs/v3.5.12/CAROUSEL-PHASE-0-PLAN.md
docs/v3.5.12/CAROUSEL-PHASE-0-REPORT.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
products/reference-implementations/axismundi-lab/stylesheets/components.css §30
products/reference-implementations/axismundi-lab/style-guide.html #components-carousel
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel.css
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel.js
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel-pattern.html
products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-AUDIT.md
products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-ONTOLOGY-CHECK.md
products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-VISUAL-QA.md
https://m3.material.io/components/carousel/specs
https://m3.material.io/components/carousel/guidelines
```

The M3 pages were read via Playwright text extraction because they are
JavaScript-rendered.

---

## §2 — Phase 0 Verdict Carry-Forward

Locked decisions:

```txt
1. Carousel uses 4-doc shape.
2. lab-carousel.js is component-owned extracted runtime evidence.
3. Reduced motion is a Phase 2 blocker.
4. Arrow keys exist; Home/End gap must be handled.
5. .ax-carousel is canonical; .ax-material-slider is historical adapter naming.
6. Gallery is not Carousel; conditional binding only.
7. Ripple is NONE for track/items and conditional through composed controls.
8. Existing v3.3.2 docs remain historical/reference docs.
```

---

## §3 — Component Definition

Carousel is a scrollable collection of visual items that move on and off screen.
In Axismundi it has two layers:

```txt
Static public primitive:
  .ax-carousel
  .ax-carousel__track
  .ax-carousel__item
  .ax-carousel__item-media
  .ax-carousel__item-label

Lab interaction runtime:
  [data-material-slider]
  .ax-material-slider
  .ax-material-slider__track
  .ax-material-slide
```

Canonical component name:

```txt
Carousel
```

Canonical primitive selector:

```txt
.ax-carousel
```

Historical adapter selector:

```txt
.ax-material-slider
```

The adapter selector may remain in Phase 2 if removing it would increase risk,
but new documentation and user-facing naming must frame the component as
Carousel, not "Material slider".

---

## §4 — M3 Variant Coverage

M3 layouts extracted:

| M3 layout | v3.5.12 status | Notes |
| --- | --- | --- |
| Multi-browse | CURRENT partial | Static baseline and lab runtime both demonstrate large/medium/small roles. |
| Uncontained | CURRENT partial | Static baseline and lab runtime support uncontained form. |
| Hero | CURRENT partial | Static baseline and lab runtime support hero form. |
| Center-aligned hero | PHASE 2 candidate | Lab runtime has centered toggle; pattern needs explicit specimen. |
| Uncontained multi-aspect ratio | PHASE 2 candidate | Needs stable media specimens and measurement table. |
| Full-screen | DEFERRED unless Phase 2 safely isolates | Page/viewport behavior is integration-heavy. |

Phase 2 must not claim complete M3 layout coverage unless each layout has a
specimen and Playwright geometry evidence.

---

## §5 — Static Public Primitive Contract

Baseline §30 currently owns the static primitive:

```txt
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

Static primitive requirements:

```txt
renders without JavaScript
uses horizontal overflow / scroll snap
uses token-driven surfaces, shape, and spacing
does not require generated DOM
does not attach runtime behavior by default
```

---

## §6 — Interactive Runtime Boundary

Runtime behavior is owned by `CAROUSEL-RUNTIME-AUDIT.md`.

SPEC only records the public component states:

```txt
rest
hover on controls
focus on viewport / controls
active slide
dragging
reduced-motion
no-JS fallback
```

Do not duplicate the runtime algorithm in SPEC. The runtime doc owns active
index, pointer drag, transforms, generated dots, and keyboard behavior.

---

## §7 — Navigation And Control Composition

Carousel navigation controls compose existing controls:

```txt
prev / next buttons -> icon-button-like control
layout / SPV knobs  -> lab-only demo controls
dots                -> runtime-generated pagination controls
```

Icon-system state:

```txt
CURRENT conditional.
```

Phase 2 should prefer Material Symbols for prev/next controls if the pattern is
edited. Existing inline SVG is historical and lab-scoped, not a new precedent.

Ripple state:

```txt
Track/items: NONE.
Composed controls: conditional via icon-button/control consumer route.
```

Do not add `data-ax-ripple` to carousel media items.

---

## §8 — Principle 1 / Principle 2 Application

Principle 1:

```txt
Visible controls must be real runtime controls.
```

Implications:

```txt
prev/next buttons must move the carousel if shown
dots must update active slide if shown
layout/SPV controls must be clearly marked as lab catalog controls
Show all affordance must be a real link/button if present
```

Principle 2:

```txt
Use native semantics first.
```

Implications:

```txt
buttons for prev/next/dots
region/group labelling for viewport and control groups
no div role="button" control shims
ARIA state must reflect runtime state, not static decoration
```

---

## §9 — Accessibility Contract

SPEC-level accessibility requirements:

```txt
viewport has an accessible label
keyboard users can reach and leave the carousel
active item / position is discoverable through controls or status
reduced motion is honored
visible focus is present
there is a non-horizontal path to all items when the carousel sits on a vertically scrolling page
```

The last requirement maps to M3 guidance around a "Show all" affordance. It can
be represented as a pattern recommendation in v3.5.12; a WordPress editor UI is
not required.

---

## §10 — Dependency Profile

| Provider | Consumer-state | Notes |
| --- | --- | --- |
| `components.css §30` | CURRENT | Static public Carousel primitive. |
| `lab-carousel.*` | CURRENT | Extracted lab runtime and pattern. |
| `icon-system/` | CURRENT conditional | Controls should use Material Symbols when represented in updated lab specimens. |
| `ripple/` | NONE / conditional | None on track/items; possible on composed controls only. |
| `state-layer §0` | CURRENT conditional | Applies to controls, not media items. |
| `gallery` / media content | DISTINCT but COUPLED | Gallery can feed Carousel only under explicit conditions. |

---

## §11 — M3 Alignment Gaps

Known gaps at Phase 1 entry:

```txt
reduced-motion support missing in lab runtime
Home/End keyboard support missing in lab runtime
center-aligned hero not explicit as separate specimen
uncontained multi-aspect ratio not explicit as separate specimen
full-screen likely deferred
Show all accessibility affordance not represented
inline SVG controls remain in lab pattern
remote picsum images make visual QA less deterministic
```

These are not release blockers in Phase 1; they are Phase 2/3 obligations or
explicit deferrals.

---

## §12 — G1-G10 Component Readiness

| Gate | Phase 1 status |
| --- | --- |
| G1 Validator | PASS expected; docs-only. |
| G2 Baseline untouched | PASS in Phase 1. |
| G3 Publish | Deferred to Phase 5 / existing generator behavior. |
| G4 Module artifacts | Existing lab module present; Phase 2 may revise. |
| G5 CHANGELOG | Phase 5 venue. |
| G6 Static Visual QA | Phase 3 venue. |
| G7 Principle 1 | Defined in §8. |
| G8 Principle 2 | Defined in §8. |
| G9 WCAG SC accuracy | Owned with MEASUREMENT and RUNTIME docs. |
| G10 3-doc audit pattern | Modified: Carousel uses 4-doc because runtime is extracted. |

---

## §13 — G11-G16 Interaction Cross-Link

Carousel is dual-category. G11-G16 are owned by `CAROUSEL-RUNTIME-AUDIT.md`.

SPEC cross-link:

```txt
G11 hard runtime rules -> RUNTIME §4-§10
G12 runtime rules in code -> RUNTIME §11
G13 inventory accuracy -> RUNTIME §2-§3
G14 forbidden ancestor -> RUNTIME §12
G15 reduced motion -> RUNTIME §8
G16 runtime audit doc -> this 4-doc set includes CAROUSEL-RUNTIME-AUDIT.md
```

---

## §14 — Risks And Deferred Items

| Risk | Disposition |
| --- | --- |
| Reduced motion missing | Phase 2 blocker. |
| Home/End missing | Phase 2 expected fix unless RUNTIME explicitly revises contract. |
| Full-screen layout | Deferred unless safe lab-only specimen. |
| Gallery direct binding | Rejected. |
| Runtime promotion creep | Out of scope. |
| Naming drift | `.ax-carousel` canonical; adapter allowed short-term. |

---

## §15 — Phase 2 Readiness

Phase 2 may edit:

```txt
lab-carousel.css
lab-carousel.js
lab-carousel-pattern.html
limited audit-doc bookkeeping if plan says so
```

Phase 2 must not edit by default:

```txt
components.css
tokens.css
style-guide.html
blocks.css
theme.json
WordPress bindings
CHANGELOG / ROADMAP / MATRIX / state docs
```

Any baseline edit must be separately planned with a narrow diff and fallback
trigger.

---

## §16 — Verdict

Phase 5 SPEC verdict:

```txt
ALL PASS.

#1 M3 Carousel spec coverage:
  PASS. Multi-browse / hero / uncontained are represented; center/full-screen
  variants are documented as deferred/integration scope.

#2 Token-driven implementation:
  PASS. Lab runtime uses M3 motion/color/shape tokens and lab-local custom
  properties; no new baseline tokens were introduced.

#3 Pattern HTML completeness:
  PASS. Existing v3.3.2 lab pattern was updated in place for v3.5.12 runtime
  blockers and naming/caption clarity.

#4 Audit doc completeness:
  PASS. 4-doc set exists with RUNTIME-AUDIT owning G11-G16.

#5 Dependency declarations:
  PASS. icon-system conditional, ripple NONE/conditional, Gallery DISTINCT but
  COUPLED, and state-layer/control composition are declared.
```

Phase 2 update:

```txt
PASS for pattern/runtime artifact presence.
lab-carousel.css/js/pattern.html were updated in place.
Reduced-motion and Home/End blockers were addressed in the lab runtime.
Phase 3 visual QA still owns final acceptance.
```

Phase 3 / Phase 5 close:

```txt
User visual QA: PASS.
Reduced-motion blocker: CLOSED.
Home/End keyboard blocker: CLOSED.
No-JS fallback: preserved via .is-enhanced runtime marker.
Baseline files: untouched in v3.5.12.
Wave 1: 9 / 9 complete.
```

Next step:

```txt
v3.5.13 Wave 1 closure cleanup (#32 / #33 / Records sweep).
```

---

## §17 — What This Audit Does NOT Do

This audit does not:

```txt
promote carousel runtime to a public theme bundle
make core/gallery automatically render as Carousel
create an editor carousel picker
create a carousel custom post type
solve all full-screen media feed behavior
solve BACKLOG #32 or #33
replace historical v3.3.2 docs
edit baseline CSS
```
