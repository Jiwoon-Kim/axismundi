# Ripple Audit — v3.3.3


> **v3.4.8 Deletion Notice (added retrospectively):**
> The benchmark source files referenced throughout this document
> (`scripts/benchmark-interactions.js`, `stylesheets/benchmark-interactions.css`,
> and `style-guide-benchmark.html`) were **removed from the active tree at
> v3.4.8 Benchmark Surface Deletion**. Line ranges and quoted file paths in
> this document are HISTORICAL references — they describe where the source
> code lived during extraction, not where it lives now. Provenance is
> preserved in this audit document, CHANGELOG.md / ROADMAP.md, the
> v3.X.Y zip freezes, and git history.

> First Beer-CSS-derived module audit under the new
> `../../../docs/BEER-CSS-INTAKE.md` contract. Records the extraction-with-refinement
> decisions made when porting the benchmark ripple into a stand-alone lab
> module, and the per-module verdict on the five lab promotion criteria.

## Extraction record (v3.3.3)

### Source locations (before extraction)

| Asset | Path | Range / signature |
|---|---|---|
| CSS | `stylesheets/benchmark-interactions.css` | L47 (`--ax-benchmark-ripple-duration`), L74–106 (`.ax-benchmark-ripple-host` / `.ax-benchmark-ripple` / `@keyframes` / reduced-motion block) |
| JS  | `scripts/benchmark-interactions.js` | L27–64 — `enableRipple()` |

### Target locations (after extraction)

| Asset | Path |
|---|---|
| CSS | `stylesheets/lab-ripple.css` |
| JS  | `scripts/lab-ripple.js` (self-contained IIFE) |
| Demo page | `lab-ripple-pattern.html` |
| Audit (this file) | `docs/RIPPLE-AUDIT.md` |
| Cross-module contract | `../../../docs/BEER-CSS-INTAKE.md` |

### Audit-trail markers in benchmark

Per the intake contract §9, the original benchmark sections were marked
`EXTRACTED: v3.3.3 → <path>` but **not deleted**. The benchmark page
continues to load `enableRipple()` from its own copy; this allows direct
comparison between the benchmark version and the refined module during QA.

## Approach taken — extract-with-refinement, not reimplement-from-spec

The benchmark ripple structure (CSS custom-property positioning + JS pointerdown
+ `@keyframes` scale-and-fade) is sound. The Beer-CSS-isms are concentrated
in details (class prefixes, hardcoded numbers, missing scope bail-out) that
can be corrected without rewriting the core mechanism.

Verdict: **extract with seven specific refinements** (enumerated below),
not a fresh reimplementation.

### Refinements applied

| # | Issue in benchmark | Refinement in lab module | Intake contract clause |
|---|---|---|---|
| 1 | Class prefix `.ax-benchmark-ripple*` | Renamed to `.ax-ripple-host` / `.ax-ripple` | §2 (naming) |
| 2 | Hardcoded `opacity: 0.22` | Replaced with module-local `--ax-ripple-opacity: 0.16` declared at `:root`, with a comment explaining the M3 state-layer-opacity rationale (M3 does not define a ripple-specific opacity token; the dragged-state-layer-opacity value of 0.16 is the closest semantic match). | §3 (tokenization) |
| 3 | Hardcoded `--ax-benchmark-ripple-duration: 780ms` | Replaced with `var(--md-sys-motion-curve-slow-spatial-duration)` (650ms). The benchmark value was longer than any M3 motion token; slow-spatial is the M3-canonical match for a position-based effect. | §3 (tokenization) |
| 4 | Default easing on the animation | Added `var(--md-sys-motion-curve-slow-spatial)` cubic-bezier as the timing function. | §3 (tokenization) |
| 5 | Reduced-motion handling: `animation-duration: 1ms` | Changed to `animation: none` plus a static-tint-with-opacity-transition fallback path. `1ms` still fires animationstart/end and can cause a visible flash on some user agents; `none` suppresses the effect entirely while preserving feedback via a brief opacity transition. | §4 (reduced-motion) |
| 6 | No `.prose` / federation bail-out in JS | Added an explicit `.closest('.prose, .wp-block-post-content, .entry-content, [contenteditable]')` check that returns early before the allowlist match. | §1 (component scope) |
| 7 | `pointerdown` listener registered without `{ passive: true }` | Marked passive (handler never calls `event.preventDefault()`). | §6 (no listener proliferation) |

### Refinements deliberately NOT applied

- **`currentColor` ripple background** — left as-is. Using the host's
  current text color means the ripple naturally tracks the host's M3 role
  (e.g. white ripple on a filled `is-filled` button whose `color` is
  `on-primary`; on-surface ripple on a text-style button). Replacing with
  an explicit `--md-sys-color-*` token would either over-specify per-host
  rules or fail in some host states. Documented in
  `lab-ripple.css` header.
- **Physical positioning (`left` / `top`)** — left as-is. Ripple
  coordinates come from `event.clientX` / `event.clientY`, which are
  physical-coordinate values regardless of writing direction. The ripple
  must emanate from the visual click point in both LTR and RTL; logical
  positioning would mirror the ripple in RTL and detach it from the
  click. Documented inline in `lab-ripple.css`.

## Lineage

Visual pattern reference: Beer CSS docs page (preserved in
`compare/Beer CSS - …html`). The reference is a CSS framework for
M3-style applications; Axismundi's interpretation focuses on the
ripple behavior only, not Beer CSS's class naming or its broader
runtime helpers (intake contract §8 — no wholesale helper import).

`compare/Beer CSS - …html` is frozen reference, not authority.

## Promotion criteria — five-criterion check

Same five-criterion methodology as `INTERACTION-AUDIT.md` (v3.2.2) and
`CAROUSEL-AUDIT.md` (v3.3.2).

### 1. Works without JS

When `lab-ripple.js` does not load:
- Buttons, chips, icon buttons still render normally.
- Pointer interactions still work (clicks, focus, etc.).
- No ripple is shown — the host's existing M3 state layer (defined in
  `components.css §0 State-layer foundation`) still provides hover /
  focus / pressed feedback.

**Status: PASS by construction.** Ripple is purely additive — the
state-layer foundation is what makes the button feel responsive in the
no-JS path.

### 2. M3 state-layer compliance

This module does **not** add hover / focus state tinting — that work
is owned by `components.css §0`. Ripple is the pressed-event radial
animation on top of the state-layer system. The two compose:

- Hover: state layer ::before pseudo at hover-state-layer-opacity
  (0.08), no ripple.
- Focus-visible: state layer ::before at focus-state-layer-opacity
  (0.10), no ripple.
- Pressed: state layer ::before at pressed-state-layer-opacity (0.10)
  + ripple span at `--ax-ripple-opacity` (0.16), animating outward.
- Dragged: state layer ::before at dragged-state-layer-opacity (0.16);
  ripple finishes its keyframe independently.

There is no conflict because the ripple uses an absolutely-positioned
child span, not a pseudo-element, so the host's `::before` state-layer
selector is unaffected.

**Status: PASS.**

### 3. `prefers-reduced-motion: reduce` honored

Explicit `@media (prefers-reduced-motion: reduce)` rule in
`lab-ripple.css` sets `animation: none` and swaps to an opacity
transition; `lab-ripple.js` detects `window.matchMedia('(prefers-reduced-motion: reduce)').matches` and triggers the fade via the
`.is-fading` class after one `requestAnimationFrame`. Net effect:
no scale animation, brief static tint, ~150ms fade.

**Status: PASS** — see `CAROUSEL-VISUAL-QA.md` for the manual test
protocol pattern (Carousel audit had this as its known blocker;
ripple addresses it from the start).

### 4. Keyboard-operable

Ripple is a pointer-only effect. Keyboard activation of a button
(`Enter` / `Space`) does **not** fire `pointerdown`, so no ripple
spawns. This is intentional — keyboard users get focus-visible state
layer (Criterion 2), not the ripple. The host button's keyboard
interaction is unaffected by this module.

Caveat: if a future requirement adds a keyboard ripple (some M3
implementations do animate a ripple from the host center on Enter
press), it would be a separate keyboard-event handler. Out of scope
for v3.3.3.

**Status: PASS** for the pointer-only scope. Recorded here so future
audits know the intentional gap.

### 5. No leak into `.prose` / `post_content` / federated surfaces

`lab-ripple.js` has an explicit early bail-out:

```js
if (event.target.closest('.prose, .wp-block-post-content, .entry-content, [contenteditable]')) {
  return;
}
```

The pattern page (`lab-ripple-pattern.html`) includes two negative test
cases (`.lab-prose-box` containers wrapping `.prose` and a
`[contenteditable]` region with a valid `.ax-button` inside) so the
isolation is visually confirmable during manual QA.

**Status: PASS by explicit JS bail-out; verifiable in pattern page.**

### Verdict summary

| Criterion | Status | Blocker? |
|---|---|---|
| 1. Works without JS | PASS by construction | No |
| 2. M3 state-layer compliance | PASS | No |
| 3. `prefers-reduced-motion: reduce` honored | PASS | No |
| 4. Keyboard-operable | PASS (pointer-only by design) | No |
| 5. No `.prose` / federation leak | PASS by explicit bail-out | No |

**Verdict: PASS on all five criteria.** Ripple is eligible for
promotion to `components.css` once visual QA on the pattern page
confirms behavior in light / dark theme. Promotion path:

```
v3.3.3 — lab-ripple.{css,js,pattern.html} + this audit (current)
v3.3.x — visual QA pass on lab-ripple-pattern.html (manual)
later  — merge lab-ripple.css → components.css §X
         merge lab-ripple.js  → theme.js or new interactions.js
```

Promotion is deliberately **not** done in v3.3.3 — this version's
scope is "establish intake contract + extract first module under it".
Promotion is its own step with its own diff review.

## Module file inventory

```
products/reference-implementations/axismundi-lab/
├─ stylesheets/
│  └─ lab-ripple.css                  ← module CSS
├─ scripts/
│  └─ lab-ripple.js                   ← module JS (self-contained IIFE)
├─ lab-ripple-pattern.html            ← demo page + negative test cases
└─ docs/
   ├─ BEER-CSS-INTAKE.md              ← cross-module contract (v3.3.3)
   └─ RIPPLE-AUDIT.md                 ← this file
```

`publish_styleguide.py` was not modified. It will pick up
`lab-ripple.css` via its `*.css` glob and copy it to
`/styleguide/stylesheets/lab-ripple.css` (orphan publish artifact —
no HTML on the publish surface links to it). `lab-ripple.js` and
`lab-ripple-pattern.html` are NOT copied to the publish surface,
matching the same asymmetry that v3.3.2 carousel exhibited. Both
will be addressed in v3.4.x Lab Module Restructure (modular folder
layout + module-aware publish tool).

## Future audit cycles

Subsequent ripple audit revisions will be needed when:

- Manual visual QA against `lab-ripple-pattern.html` returns findings.
- A keyboard ripple is required (currently out of scope).
- The intake contract changes a constraint that affects this module.
- Promotion to `components.css` is approved.

Each revision appends a section to this file (not a new file).
