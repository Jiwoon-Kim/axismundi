# v3.5.6 — Ripple v2 + `data-ax-ripple` — Phase 2 Plan

> **Status**: Plan v1.0 — pending review / approval  
> **Release lane**: Foundation / infrastructure amendment  
> **Backlog scope**: #25 Ripple v2 contract + #27 `data-ax-ripple` opt-in  
> **Phase 1 source**: `products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md`  
> **Mode**: Plan-only. Do not implement until approved.

---

## §0 — Phase 2 Goal

Phase 2 implements the Ripple v2 contract defined in `RIPPLE-V2-AUDIT.md`.

Unlike Wave 1 component modules, Ripple v2 is infrastructure runtime work. JavaScript is required.

Deliverable artifacts:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple-pattern.html
```

Bookkeeping at Phase 2 close:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

Phase 2 should update only the audit verdict/readiness section to show implementation artifacts are present. Release close, matrix amendment, backlog closure, and closed consumer SPEC alignment are Phase 5.

---

## §1 — File Scope

### Edit exactly three runtime/pattern files

```txt
lab-ripple.css
lab-ripple.js
lab-ripple-pattern.html
```

### Optional Phase 2 bookkeeping edit

```txt
docs/RIPPLE-V2-AUDIT.md
```

Allowed only for:

```txt
G4 Module artifacts present: PASS at Phase 2
Phase 2 implementation verdict
Phase 3 QA checklist pointer
```

### Do not edit in Phase 2

```txt
components.css
style-guide.html
tokens.css
blocks.css
RIPPLE-AUDIT.md
Button/Icon button/Card/FAB SPEC docs
MODULE-STATUS-MATRIX.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
```

---

## §2 — `lab-ripple.css` Implementation Plan

### Token contract

Add v2 token contract inside `lab-ripple.css`:

```css
:root {
  --md-ripple-hover-color: var(--md-sys-color-on-surface);
  --md-ripple-pressed-color: var(--md-sys-color-on-surface);

  --ax-ripple-hover-color: var(--md-ripple-hover-color);
  --ax-ripple-pressed-color: var(--md-ripple-pressed-color);
  --ax-ripple-hover-opacity: var(--md-sys-state-hover-state-layer-opacity);
  --ax-ripple-pressed-opacity: 0.16;
  --ax-ripple-duration: var(--md-sys-motion-curve-slow-spatial-duration);
  --ax-ripple-easing: var(--md-sys-motion-curve-slow-spatial);
}
```

Do not edit `tokens.css`. These are module-level public tokens.

### Host classes

Use provider-owned classes:

```css
.ax-ripple-host
.ax-ripple-host--bounded
.ax-ripple-host--unbounded
.ax-ripple
.ax-ripple.is-fading
```

Stable public selector:

```css
[data-ax-ripple]
```

It is acceptable for `lab-ripple.css` to style `[data-ax-ripple]` because this is the stable public contract for the provider.

### Bounded behavior

Bounded host:

```txt
position: relative
overflow: hidden
```

Bounded ripple:

```txt
pointer-origin geometry
clipped by host
uses --ax-ripple-pressed-color / opacity
```

### Unbounded behavior

Unbounded host:

```txt
position: relative
overflow: visible
```

Unbounded ripple:

```txt
centered geometry
circle may exceed host visual bounds
size uses explicit JS geometry, defaulting to max(host width, height) * 2
```

Phase 2 may tune multiplier during visual QA, but must keep behavior deterministic.

### Reduced motion

Keep v1 principle:

```txt
prefers-reduced-motion: reduce disables radial expansion
brief tint / opacity fade allowed
cleanup still occurs
```

---

## §3 — `lab-ripple.js` Implementation Plan

### Public namespace

Expose:

```js
window.axRipple = {
  attach(control, options),
  detach(control),
  refresh(root)
};
```

### JSDoc-style signatures

Use comments close to implementation:

```js
/**
 * @param {Element} control
 * @param {{ mode?: "bounded" | "unbounded", legacy?: boolean }} [options]
 * @returns {Element|null}
 */
function attach(control, options) {}

/**
 * @param {Element} control
 * @returns {boolean}
 */
function detach(control) {}

/**
 * @param {ParentNode} [root=document]
 * @returns {Element[]}
 */
function refresh(root) {}
```

No TypeScript file is created.

### Internal state

Use a `WeakMap`:

```js
const attached = new WeakMap();
```

Each record may hold:

```txt
mode
listener
legacy flag
```

Reason:

```txt
idempotent attach
safe detach
no memory-visible public registry
```

### Discovery model

Stable public discovery:

```js
root.querySelectorAll("[data-ax-ripple]")
```

Transitional legacy discovery:

```js
root.querySelectorAll(HOST_SELECTOR)
```

Stable contract:

```txt
[data-ax-ripple]
```

Legacy allowlist:

```txt
compatibility only
kept for v3.5.6 migration evidence
not the recommended authoring path
```

### Event model

Move from one document-level delegated listener to per-host pointer listener via `attach()`.

Reason:

```txt
attach/detach becomes real
no hidden global allowlist dependency
consumer/plugin mounts can manage lifecycle
```

Listener contract:

```txt
pointerdown only
primary button only
passive: true
disabled / aria-disabled guard
forbidden ancestor guard
no keyboard ripple in v3.5.6
```

### Geometry

Bounded:

```txt
origin = pointer position relative to host
size = max(width, height)
```

Unbounded:

```txt
origin = host center
size = max(width, height) * 2
```

Phase 2 can set:

```txt
--ax-ripple-x
--ax-ripple-y
--ax-ripple-size
```

### Cleanup

Required:

```txt
remove ripple node on animationend or transitionend
fallback timer allowed for robustness
detach removes listener and provider-owned host classes if appropriate
```

Provider-owned host classes may be removed by `detach()` only if the provider attached them.

---

## §4 — `lab-ripple-pattern.html` Implementation Plan

Pattern page should become the v2 proof surface.

Required sections:

```txt
§1 Contract overview
§2 Bounded opt-in examples
§3 Unbounded opt-in examples
§4 Existing 7 TARGET consumer coverage
§5 Newly promotable Wave 1 consumers (FAB + Card action)
§6 Dual-mode migration: data-ax-ripple vs legacy allowlist
§7 Public API demo: attach / detach / refresh
§8 Disabled and aria-disabled no-ripple
§9 Forbidden ancestor no-ripple
§10 Reduced-motion QA instructions
§11 Code snippets
§12 Cross-references
```

Minimum live specimens:

```txt
Button bounded
Icon button unbounded
Chip bounded
Menu item bounded
Nav bar item geometry specimen
Nav rail item geometry specimen
Tab bounded
FAB unbounded
Card action bounded
Disabled no-ripple
aria-disabled no-ripple
Prose no-ripple
Contenteditable no-ripple
Attach/detach demo
Refresh demo
Legacy allowlist demo
```

Static captions required:

```txt
This is a lab runtime proof surface.
Stable authoring contract is data-ax-ripple.
Legacy allowlist works only as transitional compatibility.
Keyboard activation uses focus-visible state-layer, not ripple.
```

---

## §5 — Selector And Contract Policy

Allowed unscoped provider selectors:

```css
[data-ax-ripple]
.ax-ripple-host
.ax-ripple-host--bounded
.ax-ripple-host--unbounded
.ax-ripple
```

Reason:

```txt
These are the provider's public/runtime contract selectors.
```

Forbidden in `lab-ripple.css`:

```txt
rewriting .ax-button visual styles
rewriting .ax-icon-button visual styles
rewriting .chip visual styles
rewriting .nav-bar__item visual styles
rewriting .nav-rail__item visual styles
rewriting [role='tab'] visual styles
rewriting .card visual styles
rewriting .ax-fab / .ax-fab-extended visual styles
```

Pattern-page layout selectors may use:

```css
.lab-ripple-demo
.lab-ripple-section
.lab-ripple-row
```

but must remain visual/layout-only.

---

## §6 — Consumer Mapping Implementation

Implement pattern evidence for:

| Consumer | Mode | Phase 2 pattern requirement |
|---|---|---|
| Button #1 | bounded | explicit `data-ax-ripple` |
| Icon button #2 | unbounded | explicit `data-ax-ripple="unbounded"` |
| Chip #24 | bounded | explicit `data-ax-ripple` |
| Menu #15 | bounded | explicit `data-ax-ripple` on menu item |
| Nav bar #12 | verify | include specimen, decide bounded/unbounded by visual truth |
| Nav rail #13 | verify | include specimen, decide bounded/unbounded by visual truth |
| Tabs #14 | bounded | explicit `data-ax-ripple` |
| FAB #3 + Extended FAB #4 | unbounded | explicit `data-ax-ripple="unbounded"` |
| Card #9 action | bounded | explicit `data-ax-ripple` |

Phase 2 report should record the final Nav bar/Nav rail mode chosen.

Preliminary implementation lean:

```txt
Nav bar:  unbounded for icon-item press feedback
Nav rail: unbounded for icon-item press feedback
```

But visual QA can override with evidence.

---

## §7 — Phase 2 Bookkeeping

After implementation, update only:

```txt
RIPPLE-V2-AUDIT.md
```

Allowed updates:

```txt
G4 Module artifacts present -> PASS
Phase 2 implementation close block
Nav bar/Nav rail final mode if verified
Phase 3 QA checklist remains next
```

Do not update:

```txt
CHANGELOG.md
ROADMAP.md
BACKLOG.md
MODULE-STATUS-MATRIX.md
Button/Icon button/Card/FAB SPEC docs
CURRENT-STATE.md
NEXT-SESSION.md
```

---

## §8 — Test Plan

Automated / command checks:

```powershell
python .\tools\validators\validate_theme_pilot.py
```

Expected:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

Static checks:

```txt
no @material/web import
no <md-ripple>
no components.css edit
no style-guide.html edit
no tokens.css edit
window.axRipple exists in lab-ripple.js
attach/detach/refresh functions exist
data-ax-ripple specimens exist
data-ax-ripple="unbounded" specimens exist
legacy allowlist specimen exists
forbidden ancestor specimens exist
```

Browser/manual QA:

```txt
bounded ripple clips to host
unbounded ripple centers correctly
disabled / aria-disabled no ripple
prose / contenteditable no ripple
attach demo works
detach demo stops future ripple
refresh demo attaches dynamic host
keyboard focus-visible still visible
reduced-motion removes radial expansion
```

Phase 2 may need Chrome/manual visual confirmation before Phase 3 formal close, but Phase 2 should at least provide the QA surface.

---

## §9 — Risks

### Risk 1 — Runtime rewrite exceeds plan

Mitigation:

```txt
Keep implementation in the 3 existing files.
Do not create helper modules.
Do not touch baseline.
```

### Risk 2 — Unbounded geometry visually wrong

Mitigation:

```txt
Pattern page includes Icon button, FAB, Nav bar, Nav rail specimens.
Phase 3 visual QA can tune CSS/JS before close.
```

### Risk 3 — Dual mode hides migration debt

Mitigation:

```txt
Pattern page labels legacy allowlist as transitional.
Phase 5 records future removal condition.
```

### Risk 4 — Token aliases fight static state-layer

Mitigation:

```txt
Ripple pressed wave uses ripple tokens.
Hover remains owned by §0 static state-layer.
Do not add separate hover overlay in Phase 2 unless explicitly justified.
```

### Risk 5 — `detach()` removes consumer-authored styling

Mitigation:

```txt
Detach only provider-owned listener/state/classes.
Do not remove data-ax-ripple or consumer component classes.
```

### Risk 6 — Pattern HTML becomes too large

Mitigation:

```txt
Prefer compact live specimens.
Use tables/captions instead of repeated explanatory prose.
```

---

## §10 — Non-Goals

This plan does not:

```txt
implement Phase 2
edit components.css
edit style-guide.html
edit tokens.css
edit blocks.css
import @material/web
create <md-ripple>
ship data-ax-ripple-for
ship keyboard-centered ripple
edit Button/Icon button/Card/FAB SPEC docs
edit MODULE-STATUS-MATRIX.md
close BACKLOG #25
close BACKLOG #27
update CHANGELOG.md
update ROADMAP.md
update CURRENT-STATE.md
update NEXT-SESSION.md
```

---

## §11 — Approval Gate

Plan v1.0 requires review before Phase 2 execution.

Approval routes:

```txt
approve as-is
approve with findings folded into execution
request Plan v1.1
defer Ripple v2 implementation
```

If approved, next Codex task:

```txt
Rewrite lab-ripple.css, lab-ripple.js, and lab-ripple-pattern.html per this plan.
Update RIPPLE-V2-AUDIT.md Phase 2 bookkeeping only.
Run validator.
Report browser/manual QA requirements.
```

