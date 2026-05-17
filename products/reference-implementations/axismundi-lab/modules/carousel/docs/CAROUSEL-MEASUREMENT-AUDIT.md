# Carousel — Measurement Audit

> **Status**: v3.5.12 release closed; Phase 3 visual QA PASS.  
> **Component**: Carousel #34  
> **Companions**: `CAROUSEL-SPEC-AUDIT.md`, `CAROUSEL-WP-MAPPING.md`, `CAROUSEL-RUNTIME-AUDIT.md`

---

## §0 — Audit Status

This document owns Carousel layout measurements, M3 extracted values, WCAG
measurement implications, and Playwright QA expectations.

---

## §1 — Inputs Read

```txt
docs/v3.5.12/CAROUSEL-PHASE-0-REPORT.md
products/reference-implementations/axismundi-lab/stylesheets/components.css §30
products/reference-implementations/axismundi-lab/style-guide.html #components-carousel
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel.css
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel-pattern.html
https://m3.material.io/components/carousel/specs
https://m3.material.io/components/carousel/guidelines
```

---

## §2 — M3 Extracted Measurement Table

Playwright text extraction captured these M3 measurement values:

| Layout | Measurement | Value |
| --- | --- | --- |
| Multi-browse | Leading/trailing padding | 16dp |
| Multi-browse | Top/bottom padding | 8dp |
| Multi-browse | Padding between elements | 8dp |
| Multi-browse | Small item width | 40-56dp dynamic |
| Multi-browse | Item corner radius | 28dp |
| Uncontained | Leading padding | 16dp |
| Uncontained | Top/bottom padding | 8dp |
| Uncontained | Padding between elements | 8dp |
| Uncontained | Item corner radius | 28dp |
| Hero | Leading/trailing padding | 16dp |
| Hero | Top/bottom padding | 8dp |
| Hero | Padding between elements | 8dp |
| Hero | Small item width | 40-56dp dynamic |
| Hero | Item corner radius | 28dp |
| Full-screen | Leading/trailing padding | 0dp |
| Full-screen | Top/bottom padding | 0dp |

Phase 1 does not claim a complete M3 token dump. Phase 2/3 evidence should
focus on the supported layout set.

---

## §3 — Baseline §30 Measurement Comparison

Baseline §30 declares:

```txt
--_carousel-leading: 16px
--_carousel-trailing: 16px
--_carousel-block: 8px
--_carousel-gap: 8px
.ax-carousel__track padding-block: var(--_carousel-block)
.ax-carousel__track padding-inline: var(--_carousel-leading) var(--_carousel-trailing)
.ax-carousel--uncontained --_carousel-trailing: 0px
```

Initial comparison:

| M3 value | Baseline status |
| --- | --- |
| 16dp leading/trailing | PASS for multi-browse / hero. |
| 8dp top/bottom | PASS. |
| 8dp between elements | PASS. |
| Uncontained trailing bleed | PASS partial via trailing 0px. |
| 28dp item corner | Needs Phase 2/3 computed check; baseline likely uses tokenized radius. |
| Full-screen 0dp | Not represented; likely deferred. |

---

## §4 — Lab Runtime Measurement Comparison

`lab-carousel.css` and `lab-carousel.js` compute layout dynamically:

```txt
currentProfile(perView)
fixedSizeForRole(role, width)
measure()
materialShape(progress, role)
render(offsetOverride)
```

The runtime computes:

```txt
large / medium / small roles
slide offsets
slide sizes
track translate3d
scale / opacity / shift morph values
```

Measurement risk:

```txt
CSS and JS both participate in geometry. Phase 2/3 must verify computed
geometry with Playwright instead of trusting the static CSS variables alone.
```

---

## §5 — Layout Coverage Matrix

| Layout | Baseline | Lab runtime | Phase 2 expectation |
| --- | --- | --- | --- |
| Multi-browse | Present | Present | Required specimen. |
| Hero | Present | Present | Required specimen. |
| Uncontained | Present | Present | Required specimen. |
| Center-aligned hero | Not explicit | Centered toggle exists | Required or documented as deferred. |
| Uncontained multi-aspect | Not explicit | Not explicit | Candidate specimen. |
| Full-screen | Not explicit | Not explicit | Defer unless safe. |

---

## §6 — Dynamic Widths

M3 says carousel items dynamically adapt to container width. The lab runtime
already implements dynamic widths through JS measurement. Static baseline
supports responsive scroll-snap but does not implement the same morphing
algorithm.

Phase 2 Playwright checks:

```txt
390px viewport
768px viewport
1280px viewport
multi-browse role widths
hero role widths
uncontained role widths
no horizontal page overflow
```

---

## §7 — Shape And Corner Radius

M3 extracted item corner radius:

```txt
28dp
```

Phase 2/3 must verify:

```txt
item media shell corner radius
large / medium / small item consistency
active / moving morph does not create radius flicker
reduced-motion disables animated radius/shape effects where applicable
```

This check is related to v3.5.9 pill-radius discipline but not the same token
issue: Carousel item shape is not a pill morph target.

---

## §8 — Motion And Reduced Motion

Known issue:

```txt
lab-carousel.css has transform/flex/opacity transitions.
lab-carousel.js has translate3d runtime movement.
No explicit lab-scoped prefers-reduced-motion block or matchMedia handling was found.
```

Phase 2 blocker:

```txt
Under prefers-reduced-motion: reduce, transform/shape/opacity transitions must
collapse to instant state changes or otherwise avoid animated motion.
```

Phase 3 must include Playwright reduced-motion emulation:

```txt
page.emulateMedia({ reducedMotion: 'reduce' })
```

---

## §9 — WCAG SC Applicability

Relevant SCs:

| SC | Applicability | Phase 1 note |
| --- | --- | --- |
| 2.1.1 Keyboard (A) | Carousel controls and viewport movement | Arrow keys exist; Home/End gap must be resolved or documented. |
| 2.2.2 Pause, Stop, Hide (A) | Applies if auto-moving content exists | No autoplay should be introduced in v3.5.12. |
| 2.3.3 Animation from Interactions (AAA) | Morph/drag animation | Reduced-motion handling required by project discipline. |
| 2.4.3 Focus Order (A) | Controls/dots/viewport | Focus must not trap inside carousel. |
| 2.4.7 Focus Visible (AA) | Viewport and controls | Required. |
| 2.5.1 Pointer Gestures (A) | Drag should not be the only path | Buttons/keyboard must also work. |
| 2.5.7 Dragging Movements (AA) | Drag action requires single-pointer alternative | Prev/next/keyboard/dots should satisfy alternative path. |
| 4.1.2 Name, Role, Value (A) | Controls/dots/ARIA state | `aria-current` / button labels must reflect state. |
| 1.4.3 Contrast (AA) | Labels/controls | Token contrast must be verified. |

Target-size SCs apply to controls, not passive media items:

```txt
prev/next buttons and dots must be checked.
carousel items are content surfaces unless made interactive.
```

---

## §10 — Playwright Phase 2/3 QA Plan

Required checks:

```txt
layout geometry: multi-browse / hero / uncontained
viewport matrix: 390 / 768 / 1280
no page horizontal overflow
item corner radius computed value
small item width range where applicable
prev/next button dimensions and labels
dot generation and aria-current
ArrowLeft / ArrowRight
Home / End if retained
reduced-motion emulation
no-JS fallback: disable script or inspect static scroll-snap
```

Recommended screenshots:

```txt
docs/v3.5.12/carousel-phase3-desktop-qa.png
docs/v3.5.12/carousel-phase3-mobile-qa.png
```

Screenshots are QA artifacts and should stay gitignored if the repo later has
git tracking.

---

## §11 — Phase 2 Measurement Requirements

Phase 2 plan must decide:

```txt
whether to add center-aligned hero specimen
whether to add uncontained multi-aspect ratio specimen
whether full-screen is deferred
how to replace remote images with stable local/placeholder assets, if needed
how to make reduced-motion measurable
```

---

## §12 — Verdict

Phase 5 measurement verdict:

```txt
PASS for measurement framework.
PASS for Phase 2 Playwright pre-check.
PASS for user Phase 3 visual QA.
Reduced-motion blocker closed.
```

Phase 2 pre-check evidence:

```txt
Playwright viewport matrix:
  390x844  page overflow 0, active dot generated
  768x900  page overflow 0, active dot generated
  1280x900 page overflow 0, active dot generated

Keyboard:
  End -> last dot
  Home -> first dot
  ArrowRight -> next dot
  ArrowLeft -> previous dot

Reduced motion:
  CSS transition/animation disabled under prefers-reduced-motion: reduce.
  will-change collapsed on runtime transform targets.

No-JS fallback:
  page overflow 0 at 900px;
  track remains overflow-x auto with x mandatory scroll snap;
  runtime-enhanced mode switches track to transform mode with .is-enhanced.
```

Phase 3 still owns final visual QA.

Phase 3 close:

```txt
User visual QA: PASS.
No new measurement blocker filed at close.
Full-screen remains documented as deferred/integration scope, not a v3.5.12
failure.
```

---

## §13 — Cross-References

```txt
CAROUSEL-SPEC-AUDIT.md
CAROUSEL-RUNTIME-AUDIT.md
CAROUSEL-WP-MAPPING.md
CAROUSEL-AUDIT.md
CAROUSEL-VISUAL-QA.md
docs/v3.5.12/CAROUSEL-PHASE-0-REPORT.md
```
