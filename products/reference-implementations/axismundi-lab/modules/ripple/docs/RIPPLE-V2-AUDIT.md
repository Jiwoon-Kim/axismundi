# Ripple v2 Audit — v3.5.6 Contract

> **Status**: Phase 1 contract audit — implementation pending  
> **Module**: `products/reference-implementations/axismundi-lab/modules/ripple/`  
> **Category**: Interaction Runtime Infrastructure  
> **Backlog scope**: #25 Ripple v2 contract + #27 `data-ax-ripple` opt-in  
> **Predecessor**: `RIPPLE-AUDIT.md` v3.3.3 Beer-CSS-derived current implementation audit

---

## §0 — Contract Verdict / Framing

Ripple v2 is an Axismundi-native infrastructure contract aligned with Material Web's ripple model, without importing Material Web's custom element implementation.

```txt
Align with Material Web contract:
  yes

Import <md-ripple> / @material/web:
  no

Stable declarative API:
  data-ax-ripple

Modes:
  bounded default
  unbounded explicit opt-in

JS API:
  window.axRipple.attach()
  window.axRipple.detach()
  window.axRipple.refresh()

Baseline state-layer:
  components.css §0 remains CURRENT and untouched
```

The core hierarchy remains:

```txt
static CSS state-layer foundation
  ↓ progressive enhancement
animated ripple
```

Ripple v2 does not replace hover/focus/pressed CSS state-layer behavior. It adds animated visual feedback above that foundation when JavaScript is available and the consumer opts in.

---

## §1 — Authoritative Inputs

Canonical docs:

```txt
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.1/BUTTON-PHASE-0-REPORT.md
docs/v3.5.6/RIPPLE-V2-PHASE-0-PLAN.md
docs/v3.5.6/RIPPLE-V2-PHASE-0-REPORT.md
docs/v3.5.6/RIPPLE-V2-PHASE-1-PLAN.md
```

Current module:

```txt
RIPPLE-AUDIT.md
lab-ripple.css
lab-ripple.js
lab-ripple-pattern.html
```

External spec:

```txt
Material Web Ripple
https://material-web.dev/components/ripple/
```

Spec points used:

```txt
ripple is visual state-layer feedback for hover/press
attachment modes: parent / referenced target / imperative attach
bounded + unbounded variants
position: relative container requirement
visual-only accessibility posture
tokens: --md-ripple-hover-color / --md-ripple-pressed-color
API: disabled, htmlFor/control, attach(), detach()
```

---

## §2 — Current v1 Inventory Summary

Current v3.3.3 implementation:

```txt
origin: Beer CSS-derived benchmark intake
runtime: self-contained IIFE
trigger: document-level pointerdown listener
attachment: implicit HOST_SELECTOR allowlist
shape: bounded-only
keyboard: pointer-only; keyboard uses focus-visible state-layer
tokens: --ax-ripple-opacity + currentColor
reduced motion: animation none + opacity fade
forbidden ancestors: .prose / .wp-block-post-content /
                     .entry-content / [contenteditable]
```

Current allowlist:

```txt
.ax-button
.ax-icon-button
.chip
.ax-menu__item
.nav-bar__item
.nav-rail__item
[role='tab']
```

Current gaps against v2 target:

```txt
no data-ax-ripple
no bounded/unbounded mode API
no --md-ripple-* token bridge
no attach/detach public API
no detach/dispose per host
no complete 7-TARGET pattern coverage
no consumer-state vocabulary in original audit
```

`RIPPLE-AUDIT.md` remains the historical/current-v1 audit. This document is the v2 public contract audit.

---

## §3 — Material Web Alignment Contract

| Material Web concept | Axismundi v2 contract | Notes |
|---|---|---|
| Ripple communicates hover/press state through visual layer | Animated progressive enhancement above `components.css §0` | §0 remains source of static state-layer truth |
| Parent attachment | `[data-ax-ripple]` on host | Stable public API |
| Referenced target | Deferred | `data-ax-ripple-for` is not in v3.5.6 |
| Imperative attach | `window.axRipple.attach(control, options?)` | Required |
| Imperative detach | `window.axRipple.detach(control)` | Required |
| Bounded | default mode | Required |
| Unbounded | `data-ax-ripple="unbounded"` | Required |
| Tokens | `--md-ripple-hover-color`, `--md-ripple-pressed-color` | Required aliases |
| Accessibility | visual-only, no AT node | Required |
| Custom element | no `<md-ripple>` import | WordPress-compatible implementation |

Axismundi deliberately keeps two extensions not specified by Material Web:

```txt
forbidden ancestor bail-out
prefers-reduced-motion contract
```

Both are required by the project boundary model.

---

## §4 — Public Declarative API

Stable host opt-in:

```html
<button class="ax-button" data-ax-ripple>
  <span class="ax-button__label">Save</span>
</button>
```

Mode values:

| Markup | Meaning |
|---|---|
| `data-ax-ripple` | bounded |
| `data-ax-ripple=""` | bounded |
| `data-ax-ripple="bounded"` | bounded |
| `data-ax-ripple="unbounded"` | unbounded |
| unknown value | bounded fallback |

Unknown values:

```txt
Runtime falls back to bounded.
No exception is thrown.
Optional debug warning is allowed but not required.
```

Disabled opt-out:

```html
<button data-ax-ripple disabled>...</button>
<button data-ax-ripple aria-disabled="true">...</button>
```

Disabled or aria-disabled hosts do not ripple.

Referenced target:

```txt
data-ax-ripple-for is DEFERRED.
```

Reason:

```txt
All current TARGET consumers can use host-on-self attachment.
Referenced attachment introduces ID lifecycle and serialization complexity.
Imperative attach() covers dynamic/editor use cases for v3.5.6.
```

---

## §5 — Public JavaScript API

Required global:

```js
window.axRipple
```

Required methods:

```js
window.axRipple.attach(control, options?)
window.axRipple.detach(control)
window.axRipple.refresh(root?)
```

### `attach(control, options?)`

Contract:

```txt
Attach ripple behavior to a single host/control.
Returns the control or a disposable handle? Phase 2 may choose.
Must be idempotent.
Must not duplicate listeners on repeated attach.
Must respect disabled/aria-disabled and forbidden ancestors.
```

Options:

```js
{
  mode: "bounded" | "unbounded",
  disabled: boolean
}
```

Phase 2 may keep options minimal if the data attribute fully covers mode.

### `detach(control)`

Contract:

```txt
Detach ripple behavior from a single host/control.
Remove per-host state and listeners if any.
Do not remove consumer-authored classes or semantics.
Must be safe when called on an unattached control.
```

### `refresh(root?)`

Contract:

```txt
Scan root (or document) for [data-ax-ripple] controls and attach them.
Useful for WordPress/editor dynamic mounts.
Idempotent.
```

`refresh()` is the bridge between declarative markup and dynamic DOM insertion.

---

## §6 — CSS Token Contract

Ripple v2 exposes both Material Web-compatible aliases and Axismundi-native internal tokens.

Required public tokens:

```txt
--md-ripple-hover-color
--md-ripple-pressed-color
--ax-ripple-hover-color
--ax-ripple-pressed-color
--ax-ripple-hover-opacity
--ax-ripple-pressed-opacity
```

Recommended defaults:

```css
:root {
  --md-ripple-hover-color: var(--md-sys-color-on-surface);
  --md-ripple-pressed-color: var(--md-sys-color-on-surface);

  --ax-ripple-hover-color: var(--md-ripple-hover-color);
  --ax-ripple-pressed-color: var(--md-ripple-pressed-color);
  --ax-ripple-hover-opacity: var(--md-sys-state-hover-state-layer-opacity);
  --ax-ripple-pressed-opacity: 0.16;
}
```

Alias direction:

```txt
Material Web aliases provide color values.
Axismundi internal tokens consume those aliases.
Opacity remains Axismundi/system-token governed.
```

Rationale:

```txt
Downstream authors familiar with Material Web can set --md-ripple-*.
Axismundi implementation can use --ax-ripple-* consistently.
No circular alias defaults.
```

Pressed wave:

```txt
Animated wave uses --ax-ripple-pressed-color and
--ax-ripple-pressed-opacity.
```

Hover:

```txt
§0 static state-layer remains the primary hover visual.
V2 may expose hover color for future/provider completeness, but Phase 2
must not duplicate or fight the baseline hover pseudo-element.
```

---

## §7 — Bounded And Unbounded Variants

### Bounded

Default mode:

```html
<button data-ax-ripple>...</button>
<button data-ax-ripple="bounded">...</button>
```

Expected behavior:

```txt
ripple starts at pointer location
ripple is clipped to host border radius
host gets position: relative if needed
host clips overflow through provider class
```

Primary consumers:

```txt
Button #1
Chip #24
Menu #15
Tabs #14
Card #9 action/interactive surface
```

### Unbounded

Explicit mode:

```html
<button data-ax-ripple="unbounded">...</button>
```

Expected behavior:

```txt
ripple is centered on host/control
ripple may exceed host visual bounds
ripple remains non-interactive and aria-hidden
geometry uses a stable state-layer size
```

Primary consumers:

```txt
Icon button #2
FAB #3 + Extended FAB #4
Nav bar #12
Nav rail #13
```

### Consumer mode nuance

Nav bar and nav rail use visually distinct item containers. Phase 2 initially tested them as unbounded-preferred, but visual QA found the unbounded ripple too large and misaligned with the icon/state-layer geometry.

```txt
Nav bar:  bounded
Nav rail: bounded
```

This keeps ripple coverage within the item surface and avoids transient horizontal scroll in the nav rail demo.

---

## §8 — State-Layer Hierarchy

The Ripple v2 contract must never blur this boundary:

```txt
components.css §0:
  static state-layer foundation
  hover/focus/pressed/dragged opacity rules
  no JS dependency

ripple/:
  animated visual enhancement
  JS-enabled
  opt-in
  disposable
```

No-JS path:

```txt
If lab-ripple.js fails or is not loaded, consumers still have native
interaction and CSS state-layer feedback.
```

No replacement:

```txt
Ripple v2 does not remove, rewrite, or supersede has-state-layer.
```

Consumer docs should continue to say:

```txt
state-layer foundation = CURRENT
ripple/ = TARGET enhancement
```

or for newly promoted consumers:

```txt
ripple/ = TARGET after v3.5.6 alignment
```

---

## §9 — Accessibility And Reduced Motion

Accessibility contract:

```txt
ripple DOM nodes are aria-hidden="true"
ripple DOM nodes are not focusable
ripple does not alter accessible names
ripple does not add aria-live output
ripple does not replace visible focus indicators
ripple does not create fake controls
```

Keyboard decision:

```txt
v3.5.6 Ripple v2 remains pointer-only.
Keyboard users receive native focus and components.css §0 focus-visible
state-layer feedback.
```

Rationale:

```txt
The current module is pointer-only by design.
Adding keyboard-centered ripple would add new event scope without a
current consumer requirement.
Native focus-visible clarity is more important than decorative parity.
```

Future candidate:

```txt
Keyboard-centered ripple may be reconsidered in a future runtime behavior
release if Material fidelity or user testing requires it.
```

Reduced motion:

```txt
prefers-reduced-motion: reduce disables radial expansion.
brief static opacity/tint acknowledgement is allowed.
cleanup still occurs.
```

---

## §10 — Forbidden Ancestors / WordPress-Federation Safety

V2 retains the v1 forbidden-ancestor bail-out:

```txt
.prose
.wp-block-post-content
.entry-content
[contenteditable]
```

Reason:

```txt
Theme-side interaction runtime must not leak into long-form prose,
federated content, or editor-owned surfaces.
```

Provider behavior:

```txt
If event target or host is inside a forbidden ancestor, do nothing.
Do not attach.
Do not insert a ripple span.
Do not throw.
```

This is an Axismundi extension beyond Material Web and remains required.

---

## §11 — Consumer-State Mapping

| Consumer | v3.5.4 state | v3.5.6 intended state | Mode | Notes |
|---|---|---|---|---|
| Button #1 | TARGET | TARGET | bounded | Closed Wave 1 action control. |
| Icon button #2 | TARGET | TARGET | unbounded | Icon-only circular surface. |
| Chip #24 | TARGET | TARGET | bounded | Legacy audit remains unedited unless specimens change. |
| Menu #15 | TARGET | TARGET | bounded | Rectangular menu item surface. |
| Nav bar #12 | TARGET | TARGET | bounded | Visual QA found unbounded mismatched the icon/state-layer geometry. |
| Nav rail #13 | TARGET | TARGET | bounded | Visual QA found unbounded too large and able to cause transient horizontal scroll. |
| Tabs #14 | TARGET | TARGET | bounded | Tab indicator remains separate concern. |
| FAB #3 + Extended FAB #4 | CANDIDATE | TARGET after v3.5.6 | unbounded | Closed Wave 1; newly promotable. |
| Card #9 action/interactive | CANDIDATE | TARGET after v3.5.6 | bounded | Base visual card remains NONE. |
| FAB menu #5 | CANDIDATE | CANDIDATE | TBD | Await own audit. |
| Button group #6 | CANDIDATE | CANDIDATE | TBD | Await own audit. |
| Split button #7 | CANDIDATE | CANDIDATE | TBD | Await own audit. |
| Toolbar #8 | CANDIDATE | CANDIDATE | TBD | Await own audit. |
| App bar #11 action slots | CANDIDATE | CANDIDATE | TBD | Await own audit. |
| List #33 item hover/action | CANDIDATE | CANDIDATE | TBD | Await own audit. |
| Card #9 base visual card | NONE | NONE | none | Not an interactive ripple consumer. |

Phase 5 matrix amendment should apply the intended state only after Phase 2 implementation and Phase 3 QA pass.

---

## §12 — Migration Strategy

Stable public contract:

```txt
[data-ax-ripple]
```

Transitional implementation mode:

```txt
dual mode:
  1. attach declared [data-ax-ripple] hosts
  2. preserve legacy HOST_SELECTOR compatibility during v3.5.6
```

Legacy allowlist status:

```txt
implementation compatibility only
not the stable public API
candidate for removal after consumers are annotated
```

Phase 2 pattern page should include:

```txt
new explicit data-ax-ripple specimens
legacy allowlist compatibility note/specimen
bounded and unbounded specimens
disabled no-ripple specimen
forbidden ancestor no-ripple specimen
reduced-motion instructions
```

Phase 5 should record:

```txt
which consumers use explicit data-ax-ripple in pattern page
whether legacy allowlist remains
future removal condition for allowlist
```

---

## §13 — Phase 2 Implementation Requirements

Expected implementation files:

```txt
lab-ripple.css
lab-ripple.js
lab-ripple-pattern.html
```

### `lab-ripple.css`

Must implement:

```txt
bounded host/ripple styles
unbounded host/ripple styles
token bridge
reduced-motion behavior
no baseline state-layer mutation
```

### `lab-ripple.js`

Must implement:

```txt
data-ax-ripple discovery
attach(control, options?)
detach(control)
refresh(root?)
disabled/aria-disabled guard
forbidden ancestor guard
pointer-only trigger
node cleanup
no duplicate listeners
legacy HOST_SELECTOR compatibility if approved
```

### `lab-ripple-pattern.html`

Must demonstrate:

```txt
bounded Button
unbounded Icon button
bounded Chip
bounded Menu item
Nav bar / Nav rail geometry decision evidence
Tabs
FAB unbounded
Card action bounded
disabled no-ripple
forbidden ancestor no-ripple
reduced-motion QA instructions
API notes
```

---

## §14 — Phase 3 QA Requirements

Phase 3 must verify:

```txt
bounded ripple clips to host
unbounded ripple centers correctly
pointer origin correct for bounded mode
disabled hosts do not ripple
aria-disabled hosts do not ripple
forbidden ancestors do not ripple
reduced-motion disables radial expansion
detach() prevents future ripples
refresh() attaches dynamic data-ax-ripple hosts
legacy allowlist compatibility behaves as documented
keyboard focus-visible state-layer remains visible
no AT-visible ripple nodes
no baseline visual regression
```

Chrome/manual visual QA should include:

```txt
light theme
dark theme
Button
Icon button
Chip
Menu
Nav bar
Nav rail
Tabs
FAB
Card action
```

---

## §15 — G1-G5 + G22-G26 Readiness

| Gate | Phase 5 status | Notes |
|---|---|---|
| G1 Validator 1.000 | PASS | Validator remains 1.000 / 1.000 / 1.000 / 1.000 PASS after Phase 5 mechanical close. |
| G2 Baseline untouched | PASS | No baseline edits in Phase 1, Phase 2, Phase 3, or Phase 5. |
| G3 Publish runs cleanly | N/A | No publish-surface mutation in this infrastructure cycle. |
| G4 Module artifacts present | PASS (Phase 2 closed) | `lab-ripple.css`, `lab-ripple.js`, and `lab-ripple-pattern.html` implement the v2 contract. |
| G5 CHANGELOG | PASS | v3.5.6 CHANGELOG entry records Ripple v2 contract, `data-ax-ripple`, visual QA corrections, and consumer alignment. |
| G22 Multi-consumer requirement | PASS | v3.5.6 graph: 9 TARGET surfaces plus 6 remaining CANDIDATE surfaces. |
| G23 Semantic neutrality | PASS by contract | `data-ax-ripple` opt-in avoids selector-specific API. |
| G24 Boundary rules | PASS by contract | §0 baseline untouched; provider/consumer split explicit. |
| G25 Independent audit doc | PASS | This `RIPPLE-V2-AUDIT.md` is the independent v2 contract doc. |
| G26 Public dependency contract | PASS | Data attributes, JS API, tokens, modes, migration, and consumer responsibilities documented and implemented in lab runtime. |

Phase 2 close:

```txt
lab-ripple.css:
  v2 token bridge, bounded/unbounded provider classes, reduced-motion path

lab-ripple.js:
  data-ax-ripple discovery, dual-mode legacy allowlist compatibility,
  attach/detach/refresh API, forbidden ancestor guard, pointer-only trigger

lab-ripple-pattern.html:
  12-section v2 proof surface covering 7 TARGET consumers, newly promotable
  FAB/Card action consumers, disabled/forbidden cases, API demos, and reduced
  motion instructions

Nav bar / Nav rail:
  changed to bounded after visual QA. Unbounded was visually too large,
  misaligned with the icon/state-layer geometry, and caused transient
  horizontal scroll in the nav rail demo. Pattern markup now follows the
  baseline wrapper structure: nav-bar uses nav-bar__icon/nav-bar__label,
  and nav-rail keeps has-state-layer on nav-rail__icon rather than on the
  outer nav-rail__item.
```

Phase 3 + Phase 5 close:

```txt
Phase 3 Static Visual QA:
  PASS after two user-verified correction rounds.

Correction 1:
  Nav bar and Nav rail moved from unbounded-preferred hypothesis to bounded
  TARGET. The Phase 1 audit's implementation-verified caveat was validated
  honestly by visual QA.

Correction 2:
  Nav bar/Nav rail pattern markup was realigned to baseline wrapper
  structure so state-layer geometry and ripple geometry use the same host
  assumptions.

Correction 3:
  Tabs pattern markup was realigned from the invalid `.tab` demo class to
  baseline `.tabs > .tabs__tab` structure. Tab ripple remains bounded, but
  the host now receives the baseline 48px label-only tab height and flex
  width behavior.

Phase 5:
  Matrix row #36 amended, closed Wave 1 consumer SPEC notes aligned,
  BACKLOG #25 + #27 closed, CHANGELOG/ROADMAP updated.
```

---

## §16 — Risks And Deferred Items

### Risk 1 — Token bridge implementation ambiguity

Phase 2 must avoid circular token defaults.

### Risk 2 — Nav bar / nav rail geometry

Resolved during visual QA: bounded is the truthful v3.5.6 mode for Nav bar and Nav rail. Unbounded remains appropriate for Icon button and FAB, but not for the current nav item geometry.

### Risk 3 — Legacy allowlist longevity

Dual mode is safe for migration but should not become permanent hidden API.

Deferred item:

```txt
legacy allowlist removal after consumers are annotated
```

### Risk 4 — Keyboard-centered ripple

Deferred item:

```txt
keyboard-triggered centered ripple
```

Not in v3.5.6 unless explicitly re-approved.

### Risk 5 — `data-ax-ripple-for`

Deferred item:

```txt
referenced attachment equivalent to Material Web's for/htmlFor path
```

Not in v3.5.6 because no current TARGET consumer requires it.

### Risk 6 — Closed consumer side-edits

Phase 5 must keep Button/Icon button/Card/FAB notes minimal and factual.

---

## §17 — References

Phase docs:

```txt
../../../../../docs/v3.5.6/RIPPLE-V2-PHASE-0-PLAN.md
../../../../../docs/v3.5.6/RIPPLE-V2-PHASE-0-REPORT.md
../../../../../docs/v3.5.6/RIPPLE-V2-PHASE-1-PLAN.md
```

Current/historical ripple audit:

```txt
RIPPLE-AUDIT.md
```

Runtime precedent:

```txt
../../popover/docs/POPOVER-AUDIT.md
```

Closed Wave 1 consumer docs:

```txt
../../button/docs/BUTTON-SPEC-AUDIT.md
../../icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
../../card/docs/CARD-SPEC-AUDIT.md
../../fab/docs/FAB-SPEC-AUDIT.md
```

External spec:

```txt
https://material-web.dev/components/ripple/
```

---

## §18 — What This Audit Does NOT Do

This audit does not:

```txt
implement lab-ripple.css v2
implement lab-ripple.js v2
edit lab-ripple-pattern.html
edit RIPPLE-AUDIT.md
edit components.css
edit style-guide.html
edit tokens.css
edit blocks.css
edit Button/Icon button/Card/FAB SPEC docs
edit MODULE-STATUS-MATRIX.md
close BACKLOG #25
close BACKLOG #27
update CHANGELOG.md
update ROADMAP.md
update CURRENT-STATE.md
update NEXT-SESSION.md
import @material/web
create <md-ripple>
ship keyboard-centered ripple
ship data-ax-ripple-for
```
