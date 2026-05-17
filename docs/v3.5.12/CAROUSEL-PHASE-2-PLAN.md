# v3.5.12 Carousel #34 — Phase 2 Plan

> **Status**: Plan-only. Await approval before implementation.  
> **Component**: Carousel #34  
> **Category**: Component Full-Spec + Interaction  
> **Phase 0 source**: `docs/v3.5.12/CAROUSEL-PHASE-0-REPORT.md`  
> **Phase 1 source**: Carousel 4-doc audit set  
> **Core Phase 2 question**: close the v3.3.2 lab runtime gaps without mutating baseline.

---

## §0 — Executive Summary

Phase 2 should update the existing Carousel lab module, not create a new module.

Allowed deliverable edits:

```txt
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel.css
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel.js
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel-pattern.html
```

Phase 2 must fix two blockers before Phase 3:

```txt
1. Reduced motion support for lab runtime/CSS.
2. Home / End keyboard navigation.
```

Phase 2 keeps:

```txt
.ax-carousel          canonical component primitive
.ax-material-slider   historical lab-runtime adapter selector
```

No baseline edits in Phase 2.

---

## §1 — File Scope

### §1.1 Deliverable artifacts

Exactly three existing lab files may be edited:

```txt
lab-carousel.css
lab-carousel.js
lab-carousel-pattern.html
```

Expected roles:

```txt
lab-carousel.css:
  add reduced-motion rules, stabilize adapter styling, keep lab scope.

lab-carousel.js:
  add Home/End, add reduced-motion awareness where runtime movement needs it,
  preserve ArrowLeft/ArrowRight, pointer drag, dots, prev/next, ARIA updates.

lab-carousel-pattern.html:
  clarify static catalog captions, add missing specimens if needed, replace
  inline SVG nav controls with Material Symbols if low-risk, document Show all
  accessibility affordance.
```

### §1.2 Minimal Phase 2 bookkeeping

Allowed after implementation:

```txt
products/reference-implementations/axismundi-lab/modules/carousel/docs/
  CAROUSEL-SPEC-AUDIT.md
  CAROUSEL-MEASUREMENT-AUDIT.md
  CAROUSEL-RUNTIME-AUDIT.md
```

Minimal updates only:

```txt
SPEC:
  Pattern/runtime artifacts present -> Phase 2 PASS/TBD note.

MEASUREMENT:
  Playwright Phase 2 pre-check evidence summary.

RUNTIME:
  G12 implementation evidence:
    reduced-motion implemented
    Home/End implemented
    ARIA/dots/keyboard preserved
```

Do not update:

```txt
CURRENT-STATE.md
NEXT-SESSION.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
```

### §1.3 Explicitly forbidden in Phase 2

Do not edit:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/ontology-theme-pilot/theme.json
bindings/wordpress-material3/binding_map.json
```

Do not create:

```txt
public carousel runtime bundle
window.axCarousel API
WordPress carousel block
block editor carousel picker UI
custom slide schema
carousel custom post type
```

---

## §2 — Reduced-Motion Implementation Lock

Reduced motion is a hard Phase 2 blocker.

Required CSS handling:

```css
@media (prefers-reduced-motion: reduce) {
  .ax-material-slider__track,
  .ax-material-slide,
  .ax-material-slide__shell,
  .ax-material-slide__shell img,
  .ax-material-slide__label,
  .ax-material-slider-demo__choice,
  .ax-material-slider-demo__dot {
    transition-duration: 0ms;
    animation-duration: 0ms;
  }
}
```

Exact selector list may change during implementation, but must cover:

```txt
track transform
slide flex-basis
shell inline-size / transform
image transform
label opacity
dots/control transitions
```

Required JS handling:

```txt
Detect reduced motion with matchMedia('(prefers-reduced-motion: reduce)').
Avoid animated/interpolated drag behavior where it would create motion.
Keep state changes instant and deterministic.
Preserve no-JS fallback.
```

Phase 3 entry condition:

```txt
Playwright reduced-motion emulation confirms transition-duration collapses and
keyboard/control state changes still work.
```

---

## §3 — Keyboard Implementation Lock

Home/End is a hard Phase 2 blocker unless the plan is revised before execution.

Required behavior:

```txt
ArrowLeft   -> previous item
ArrowRight  -> next item
Home        -> first item
End         -> last item
```

Implementation constraints:

```txt
Prevent default only for handled keys.
Keep focus on the carousel viewport.
Do not trap Tab / Shift+Tab.
Preserve existing prev/next/dot behavior.
```

Suggested implementation shape:

```js
if (event.key === "Home") {
  event.preventDefault();
  state.index = 0;
  render();
}
if (event.key === "End") {
  event.preventDefault();
  state.index = maxIndex();
  render();
}
```

Phase 3 entry condition:

```txt
Playwright confirms ArrowLeft/ArrowRight/Home/End update active dot and
aria-current.
```

---

## §4 — Runtime Preservation Lock

Existing runtime behavior must remain intact:

```txt
prev/next buttons
generated dots
aria-current on dots
aria-pressed on layout controls
aria-pressed on SPV controls
pointer drag
resize recalculation
no-JS fallback
```

No public API:

```txt
Do not create window.axCarousel.
Do not globalize attach behavior.
Do not add public destroy/refresh API in v3.5.12.
```

Idempotence:

```txt
Do not duplicate generated dots if setup runs once as currently intended.
If implementation touches setup flow, add an idempotence guard.
```

---

## §5 — Pattern HTML Plan

`lab-carousel-pattern.html` should remain the only HTML surface for the lab
runtime.

Allowed updates:

```txt
add or clarify reduced-motion / keyboard QA captions
add Show all / view-all affordance note or specimen
add center-aligned hero specimen if low-risk
add uncontained multi-aspect ratio specimen if low-risk
replace inline SVG prev/next with Material Symbols spans if low-risk
replace remote-only media with more deterministic placeholders if needed
```

Required captions:

```txt
Static/public .ax-carousel specimens demonstrate baseline scroll-snap.
Interactive .ax-material-slider specimen demonstrates lab runtime.
Gallery is not automatically Carousel.
Reduced-motion and keyboard support are v3.5.12 runtime requirements.
```

Do not add:

```txt
full-screen page takeover unless Phase 2 plan is revised
autoplay
editor UI
remote data fetching logic
```

---

## §6 — Naming Policy

Canonical:

```txt
.ax-carousel
```

Adapter:

```txt
.ax-material-slider
```

Phase 2 may retain the adapter selector to avoid a risky rewrite. Any new
visible copy should use "Carousel" rather than "Material slider" unless it is
explicitly describing historical adapter internals.

Do not introduce a third naming family.

---

## §7 — Dependency And Consumer Policy

Ripple:

```txt
Track/items: NONE.
Prev/next controls: optional composition route only.
Do not add data-ax-ripple to carousel media items.
```

Icon system:

```txt
CURRENT conditional for prev/next controls if Material Symbols are used.
```

State-layer:

```txt
Controls may use state layer.
Media items are content surfaces, not generic state-layer hosts.
```

Gallery:

```txt
DISTINCT but COUPLED. Do not auto-bind Gallery to Carousel.
```

---

## §8 — Playwright Pre-Check Plan

Run after implementation and before Phase 3 handoff.

Required checks:

```txt
1. Open lab-carousel-pattern.html.
2. 390 / 768 / 1280 viewport screenshots or computed geometry.
3. No page horizontal overflow.
4. Multi-browse / hero / uncontained visible.
5. Prev/next click changes aria-current.
6. Dot click changes aria-current.
7. ArrowLeft / ArrowRight / Home / End update active slide.
8. Reduced-motion emulation collapses transitions.
9. No-JS fallback remains inspectable/usable.
10. Focus is visible and Tab leaves the carousel.
```

Optional checks:

```txt
center-aligned hero if added
uncontained multi-aspect if added
Material Symbols nav control geometry if converted
```

---

## §9 — Validator And Baseline Integrity

Before implementation:

```txt
python .\tools\validators\validate_theme_pilot.py
```

After implementation:

```txt
python .\tools\validators\validate_theme_pilot.py
```

Expected:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

Baseline files must remain unchanged:

```txt
components.css
tokens.css
style-guide.html
blocks.css
theme.json
```

Allowed changed implementation files:

```txt
lab-carousel.css
lab-carousel.js
lab-carousel-pattern.html
```

Allowed changed docs:

```txt
CAROUSEL-SPEC-AUDIT.md
CAROUSEL-MEASUREMENT-AUDIT.md
CAROUSEL-RUNTIME-AUDIT.md
```

---

## §10 — Edit Protocol

Use:

```txt
apply_patch
readback
validator
Playwright pre-check
```

Do not use automatic fresh Write fallback.

If readback mismatch or unexpected truncation appears:

```txt
stop
report mismatch
do not rewrite the full file unless explicitly approved or corruption is
confirmed from the Windows authoritative view
```

---

## §11 — Phase 3 Entry Criteria

Phase 3 may begin only when:

```txt
1. Reduced-motion support implemented and pre-checked.
2. Home/End implemented and pre-checked.
3. Existing ArrowLeft/ArrowRight preserved.
4. Existing prev/next/dots preserved.
5. No page horizontal overflow in viewport matrix.
6. Validator PASS.
7. Baseline files untouched.
8. Runtime audit bookkeeping records G12 Phase 2 evidence.
```

If any blocker fails:

```txt
do not ask for visual QA yet
fix inside Phase 2 scope or revise plan
```

---

## §12 — Fallback Triggers

Stop and revise if:

```txt
reduced-motion fix requires components.css/tokens.css changes
Home/End fix requires public API or global attach changes
pattern HTML needs full-screen page takeover
Gallery binding changes become necessary
Playwright finds baseline style-guide regression
validator fails after scoped edits
```

Fallback route:

```txt
open a narrow Phase 5 or BACKLOG item only after documenting the failed scope
```

---

## §13 — Non-Goals

Phase 2 does not:

```txt
edit baseline CSS
edit tokens
edit style-guide public surface
edit WordPress bindings
create editor UI
create autoplay
create public carousel API
solve full-screen immersive feed behavior
close BACKLOG #32
close BACKLOG #33
update CURRENT-STATE.md
update NEXT-SESSION.md
update CHANGELOG.md
update ROADMAP.md
update MODULE-STATUS-MATRIX.md
```

---

## §14 — Self-Check Summary

```txt
self-check:
  3 implementation files scoped: yes
  reduced-motion blocker: yes
  Home/End blocker: yes
  baseline untouched lock: yes
  .ax-carousel canonical / .ax-material-slider adapter: yes
  Phase 3 Playwright criteria: yes
  G11-G16 runtime evidence route: yes
```

