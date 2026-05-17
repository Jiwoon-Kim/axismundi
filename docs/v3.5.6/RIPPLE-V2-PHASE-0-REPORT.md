# v3.5.6 — Ripple v2 + `data-ax-ripple` — Phase 0 Report

> **Status**: Phase 0 complete — pending review / approval  
> **Release lane**: Foundation / infrastructure amendment  
> **Backlog scope**: #25 Ripple v2 contract + #27 `data-ax-ripple` opt-in  
> **Date**: 2026-05-16  
> **Mode**: Documentation-only. No Ripple v2 implementation in Phase 0.

---

## §0 — Phase 0 Verdict

Ripple v2 is correctly routed as an infrastructure module rewrite, not as a Wave 1 component cycle.

```txt
Category:
  Interaction Runtime Infrastructure

Matrix row:
  #36 ripple/

Applicable gates:
  G1-G5 universal
  G22-G26 infrastructure

Not applicable:
  G6-G10 Component Full-Spec
  G11-G16 Interaction Runtime component
  G17-G21 Baseline-only Record
```

Phase 0 confirms the core v3.5.1 Button finding:

```txt
components.css §0 static state-layer foundation = CURRENT
animated ripple = progressive enhancement above that foundation
```

The two are a hierarchy, not substitutes:

```txt
static state-layer foundation
  ↓ progressive enhancement
animated ripple
```

Phase 0 also confirms that the current v3.3.3 ripple module is valid as a lab implementation but insufficient as a stable public dependency contract. v3.5.6 should define Ripple v2 as:

```txt
Material Web contract-aligned
Axismundi-native implementation
WordPress-compatible
declarative-first via data-ax-ripple
bounded + unbounded capable
token-bridged via --md-ripple-* aliases
provider/consumer separated
```

---

## §1 — Authoritative Inputs Read

### Canonical Axismundi docs

```txt
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.1/BUTTON-PHASE-0-REPORT.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
docs/v3.5.6/RIPPLE-V2-PHASE-0-PLAN.md
```

### Current ripple module

```txt
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-AUDIT.md
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple-pattern.html
```

### Closed Wave 1 consumers

```txt
products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/card/docs/CARD-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/fab/docs/FAB-SPEC-AUDIT.md
```

### External spec

Material Web Ripple:

```txt
https://material-web.dev/components/ripple/
```

Confirmed spec points:

```txt
ripple is a state-layer visual effect for hover/press interaction
attachment modes: parent / referenced target / imperative attach()
unbounded variant exists
container needs position: relative
visual component; no dedicated assistive technology requirement
tokens: --md-ripple-hover-color / --md-ripple-pressed-color
API surface includes disabled, htmlFor, control, attach(), detach()
```

Source: [Material Web Ripple documentation](https://material-web.dev/components/ripple/)

---

## §2 — Current Ripple Module Inventory

### §2.1 Files

Current module files:

```txt
lab-ripple.css             7 KB
lab-ripple.js              7 KB
lab-ripple-pattern.html    11 KB
docs/RIPPLE-AUDIT.md       historical v3.3.3 audit
```

Current module lineage:

```txt
origin: Beer CSS-derived benchmark intake
release: v3.3.3
method: extract-with-refinement
scope: lab pattern page only
```

Current `RIPPLE-AUDIT.md` explicitly predates v3.5.4 consumer-state vocabulary and should be treated as a current/v1 historical audit, not as the v2 public contract.

### §2.2 JavaScript model

Current `lab-ripple.js` model:

```txt
self-contained IIFE
onReady(enableRipple)
document-level pointerdown listener
primary pointer button only
forbidden ancestor bail-out before allowlist matching
closest(HOST_SELECTOR) allowlist match
disabled/aria-disabled/disabled-attribute bail-out
idempotent .ax-ripple-host class add
span.ax-ripple insertion per pointerdown
animationend or transitionend cleanup
reduced-motion branch uses requestAnimationFrame + .is-fading
console.debug diagnostic
```

Current attachment model:

```txt
implicit auto-attach by selector match
no data-ax-ripple
no data-ax-ripple-for
no public attach(control)
no public detach(control)
no per-host dispose API
```

Current keyboard behavior:

```txt
pointer-only
keyboard activation does not spawn ripple
keyboard users get focus-visible state-layer from components.css §0
```

This is acceptable for v1/v3.3.3 because it is documented. Phase 1 must decide whether v2 keeps pointer-only scope or adds keyboard-centered ripple.

### §2.3 Current HOST_SELECTOR allowlist

Actual allowlist:

```txt
.ax-button
.ax-icon-button
.chip
.ax-menu__item
.nav-bar__item
.nav-rail__item
[role='tab']
```

This matches the v3.5.4 matrix TARGET bucket:

```txt
Button #1
Icon button #2
Chip #24
Menu #15
Nav bar #12
Nav rail #13
Tabs #14
```

It does not include:

```txt
FAB #3 + Extended FAB #4
Card #9 action/interactive surface
Button group #6
Split button #7
Toolbar #8
App bar #11 action slots
List #33 item hover/action surface
```

Those remain CANDIDATE until v2 makes a design decision.

### §2.4 Forbidden ancestors

Current forbidden ancestors:

```txt
.prose
.wp-block-post-content
.entry-content
[contenteditable]
```

This is an Axismundi-specific WordPress/federation safety feature. Material Web does not define it, but Axismundi should retain it in v2.

### §2.5 CSS model

Current `lab-ripple.css` model:

```txt
:root --ax-ripple-opacity: 0.16
.ax-ripple-host { position: relative; overflow: hidden; }
.ax-ripple inserted as absolute child span
background-color: currentColor
opacity: var(--ax-ripple-opacity)
transform: scale(0)
animation: ax-ripple slow-spatial duration/curve
reduced-motion: animation none + opacity transition
```

Current token gaps:

```txt
no --md-ripple-hover-color
no --md-ripple-pressed-color
no --ax-ripple-hover-color
no --ax-ripple-pressed-color
uses currentColor for press wave
no separate hover-layer token because hover remains §0 state-layer
```

Current shape:

```txt
bounded-only by host overflow clipping
no unbounded mode
no state-layer-size control
```

### §2.6 Pattern page

Current pattern page shows:

```txt
allowlisted hosts:
  Buttons
  Icon buttons
  Chips

negative tests:
  .prose
  [contenteditable]

reduced-motion test instructions
```

It does not show all 7 TARGET consumers:

```txt
Menu
Nav bar
Nav rail
Tabs
```

It also does not show candidate consumers:

```txt
FAB family
Card action/interactive
```

Phase 2 v2 pattern page should expand coverage.

---

## §3 — Material Web Alignment Comparison

| Axis | Material Web | Current Axismundi v3.3.3 | v2 target |
|---|---|---|---|
| Concept | Ripple as state-layer visual feedback for hover/press | Same concept for press; hover remains static state-layer | Align concept while preserving two-layer hierarchy |
| Parent attachment | `<md-ripple>` inside positioned parent | Implicit delegated selector | `data-ax-ripple` host opt-in |
| Referenced target | `for` / `htmlFor` style target reference | None | `data-ax-ripple-for` candidate |
| Imperative API | `attach(control)` / `detach()` | None | `window.axRipple.attach()` / `.detach()` |
| Bounded | Supported | Current default via overflow clipping | Ship in v3.5.6 |
| Unbounded | Supported by centered circular mode | Not supported | Should ship in v3.5.6 if Icon button/FAB alignment is required |
| Tokens | `--md-ripple-hover-color`, `--md-ripple-pressed-color` | `--ax-ripple-opacity`, `currentColor` | Add `--md-ripple-*` aliases and internal `--ax-*` tokens |
| Accessibility | Visual only | Visual only, aria-hidden span | Keep visual-only; no focusable/AT nodes |
| Reduced motion | Not emphasized in doc page | Implemented | Keep; make contract explicit |
| WordPress safety | Not addressed | Forbidden ancestor bail-out | Keep and document as Axismundi extension |
| Custom element | `<md-ripple>` | none | Do not import; contract-align only |

Phase 0 conclusion:

```txt
Current ripple captures the basic visual idea but lacks the stable public
contract required for infrastructure graduation.
```

---

## §4 — Infrastructure Qualification Re-Verification

### G22 — Multi-consumer requirement

Status: PASS.

```txt
TARGET consumers:     7
CANDIDATE consumers:  8
Total graph:          15
```

Ripple is clearly cross-cutting infrastructure, not a single-component helper.

### G23 — Semantic neutrality

Status: PASS with v2 condition.

Current v1 is mostly neutral but selector-bound. V2 must become more neutral by moving from hardcoded host class inference to declarative opt-in:

```txt
[data-ax-ripple]
```

Provider should not know whether the host is Button, Icon button, FAB, Card action, Menu item, or Tab. Consumers opt in and provide any required mode hints.

### G24 — Boundary rules

Status: PASS if v2 avoids baseline mutation and custom-element dependency.

Boundary lock:

```txt
components.css §0 owns static state-layer foundation
ripple/ owns animated progressive enhancement
component modules own host semantics and opt-in
plugin/editor integrations may call imperative attach/detach
```

### G25 — Independent audit doc exists

Status: CURRENT but stale.

`RIPPLE-AUDIT.md` exists but records v3.3.3 Beer-CSS-derived intake. Phase 1 should create:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

Keep `RIPPLE-AUDIT.md` as historical/current-v1 audit.

### G26 — Public dependency contract documented

Status: TARGET OF v3.5.6.

Phase 1 must define the stable contract before implementation:

```txt
data attributes
CSS custom properties
JS API
bounded/unbounded semantics
disabled behavior
reduced-motion behavior
consumer responsibilities
provider responsibilities
```

---

## §5 — Phase 0 Decisions

### Decision 1 — Do not import `<md-ripple>`

Status: LOCK.

```txt
Axismundi will align with the Material Web ripple contract.
Axismundi will not import Material Web's custom element implementation
in v3.5.6.
```

Reason:

```txt
WordPress serialization and theme/plugin contexts should not inherit a
new custom-element runtime dependency without separate approval.
```

### Decision 2 — `data-ax-ripple` is the stable declarative contract

Status: LOCK.

Stable host opt-in:

```html
<button class="ax-button" data-ax-ripple>
  ...
</button>
```

V2 should not expose the current class allowlist as the primary public API.

### Decision 3 — Keep a transitional allowlist path for migration evidence

Status: LOCK FOR PHASE 1 DESIGN.

Phase 2 may use dual mode internally:

```txt
primary:    [data-ax-ripple]
secondary:  legacy HOST_SELECTOR compatibility for current lab examples
```

But the documented stable contract should be opt-in:

```txt
public contract = data-ax-ripple
legacy allowlist = migration aid, not final API
```

### Decision 4 — Bounded + unbounded should both be in v3.5.6

Status: LOCK.

Reason:

```txt
Button and Card action surfaces are bounded-like.
Icon button and FAB are unbounded-like or at least centered circular surfaces.
Shipping bounded only would make the v3.5.6 alignment note false for
closed Icon button and FAB consumers.
```

V2 should define:

```txt
data-ax-ripple="bounded"     or default bounded
data-ax-ripple="unbounded"   explicit unbounded
```

Final attribute spelling belongs to Phase 1, but both modes should be included.

### Decision 5 — Token bridge is required

Status: LOCK.

V2 must expose Material Web-compatible aliases:

```txt
--md-ripple-hover-color
--md-ripple-pressed-color
```

And internal Axismundi aliases:

```txt
--ax-ripple-hover-color
--ax-ripple-pressed-color
```

Phase 1 should decide whether the animated wave uses pressed color only or whether hover color is consumed by a v2 hover overlay.

### Decision 6 — Public JS API required

Status: LOCK.

V2 should expose:

```txt
window.axRipple.attach(control, options?)
window.axRipple.detach(control)
```

Reasons:

```txt
matches Material Web imperative semantics
supports WordPress editor/plugin dynamic mounts
provides real detach/dispose path
reduces hidden global delegation dependency
```

### Decision 7 — Keyboard ripple remains a Phase 1 contract question

Status: OPEN.

Current v1 is pointer-only by design. Phase 1 must decide:

```txt
Option A: pointer-only ripple; keyboard uses §0 focus-visible state-layer.
Option B: keyboard activation spawns centered ripple for Enter/Space.
```

Preliminary lean:

```txt
Option A for v3.5.6 unless Material/M3 guidance or visual QA strongly
requires keyboard ripple. Preserve native focus indicator priority.
```

---

## §6 — Consumer-State Re-Evaluation

### Current v3.5.4 state

```txt
CURRENT:
  none

TARGET:
  Button #1
  Icon button #2
  Chip #24
  Menu #15
  Nav bar #12
  Nav rail #13
  Tabs #14

CANDIDATE:
  FAB #3 + Extended FAB #4
  FAB menu #5
  Button group #6
  Split button #7
  Toolbar #8
  Card #9 action/interactive surface
  App bar #11 action slots
  List #33 item hover/action surface

NONE:
  Card #9 base visual card
  non-interactive components and baseline-only records unless separately promoted
```

### Recommended v3.5.6 target

Promote audited closed Wave 1 action surfaces that now have enough evidence:

```txt
TARGET after v3.5.6:
  Button #1
  Icon button #2
  Chip #24
  Menu #15
  Nav bar #12
  Nav rail #13
  Tabs #14
  FAB #3 + Extended FAB #4
  Card #9 action/interactive surface
```

Keep unclosed/future surfaces as CANDIDATE:

```txt
FAB menu #5
Button group #6
Split button #7
Toolbar #8
App bar #11 action slots
List #33 item hover/action surface
```

Keep NONE:

```txt
Card #9 base visual card
non-interactive components
baseline-only records unless separately promoted
```

Reason:

```txt
FAB family and Card action surfaces are now closed audit surfaces with
documented native semantics and state-layer contracts.
Future components should not be promoted until their own audits land.
```

Actual matrix edit belongs to Phase 5 after v2 implementation passes QA.

---

## §7 — Proposed V2 Contract Shape

### Declarative API

Recommended Phase 1 contract:

```html
<button data-ax-ripple>...</button>
<button data-ax-ripple="bounded">...</button>
<button data-ax-ripple="unbounded">...</button>
```

Potential referenced target:

```html
<span data-ax-ripple-for="control-id"></span>
<button id="control-id">...</button>
```

Phase 1 must decide whether `data-ax-ripple-for` ships in v3.5.6 or remains future.

### CSS tokens

Recommended:

```css
:root {
  --ax-ripple-hover-color: var(--md-sys-color-on-surface);
  --ax-ripple-pressed-color: var(--md-sys-color-on-surface);
  --md-ripple-hover-color: var(--ax-ripple-hover-color);
  --md-ripple-pressed-color: var(--ax-ripple-pressed-color);
}
```

Phase 1 should refine directionality of aliases. Downstream users should be able to set either `--md-ripple-*` or `--ax-ripple-*` without surprises.

### JavaScript API

Recommended:

```js
window.axRipple.attach(control, options);
window.axRipple.detach(control);
window.axRipple.refresh(root);
```

`refresh(root)` is optional but useful if v2 keeps transitional auto-discovery for `[data-ax-ripple]`.

### Consumer responsibilities

```txt
host is a real interactive control or intentional interactive surface
host remains accessible without ripple
host opts in with data-ax-ripple
host may choose bounded/unbounded mode
host provides disabled state through native disabled or aria-disabled
host must not rely on ripple as the only focus/pressed feedback
```

### Provider responsibilities

```txt
do not mutate baseline component semantics
do not create focusable/AT-visible nodes
respect forbidden ancestors
respect disabled/aria-disabled
respect prefers-reduced-motion
provide detach/dispose path
clean up inserted ripple nodes
avoid listener proliferation
```

---

## §8 — Closed Consumer Alignment Notes

Phase 5 should add minimal v3.5.6 alignment notes to:

```txt
BUTTON-SPEC-AUDIT.md
ICON-BUTTON-SPEC-AUDIT.md
CARD-SPEC-AUDIT.md
FAB-SPEC-AUDIT.md
```

Each note should include:

```txt
consumer-state after v3.5.6
whether lab pattern uses data-ax-ripple
bounded/unbounded mode
baseline untouched statement
two-layer hierarchy statement
```

Suggested mapping:

```txt
Button:
  TARGET, bounded

Icon button:
  TARGET, unbounded or centered circular

Card:
  base visual card = NONE
  action/interactive card = TARGET, bounded

FAB family:
  TARGET, unbounded or centered circular
```

Chip v3.4.9:

```txt
Leave legacy audit docs untouched unless Phase 2 edits Chip examples.
Matrix carries Chip TARGET state.
```

---

## §9 — Risks And Dispositions

### Risk A — Custom element dependency drift

Risk:

```txt
Material Web's implementation is custom-element based.
Axismundi could accidentally import a runtime dependency that WordPress
themes/plugins are not ready to carry.
```

Disposition:

```txt
Contract-align only. Do not import <md-ripple> or @material/web in v3.5.6.
```

### Risk B — Bounded/unbounded scope creep

Risk:

```txt
Bounded-only is simpler but insufficient for Icon button/FAB truthfulness.
Unbounded adds geometry and QA surface.
```

Disposition:

```txt
Ship both bounded and unbounded in v3.5.6.
Keep mode API small and declarative.
```

### Risk C — Allowlist to opt-in migration

Risk:

```txt
Hard-switching away from HOST_SELECTOR may make existing lab pattern
specimens stop rippling until every example is annotated.
```

Disposition:

```txt
Use transitional dual mode during v3.5.6 implementation if needed.
Stable documented contract is data-ax-ripple.
```

### Risk D — Closed consumer side-edits

Risk:

```txt
Four already-closed Wave 1 consumer docs need alignment notes.
```

Disposition:

```txt
Use v3.5.4 matrix-alignment precedent. Keep notes minimal and factual.
```

### Risk E — State-layer hierarchy confusion

Risk:

```txt
Animated ripple might be mistaken as replacing components.css §0.
```

Disposition:

```txt
Lock two-layer hierarchy in every Ripple v2 doc:
  §0 state-layer foundation remains CURRENT
  v2 animated ripple is progressive enhancement above it
```

### Risk F — Current ripple audit staleness

Risk:

```txt
RIPPLE-AUDIT.md is accurate for v3.3.3 but stale for v3.5.6 vocabulary.
```

Disposition:

```txt
Create RIPPLE-V2-AUDIT.md in Phase 1.
Keep RIPPLE-AUDIT.md as v1/current historical audit.
```

### Risk G — Keyboard ripple scope

Risk:

```txt
Adding keyboard ripple may interfere with native focus semantics;
omitting it may be perceived as incomplete compared with some M3 runtimes.
```

Disposition:

```txt
Phase 1 explicit decision required.
Preliminary lean: no keyboard ripple in v3.5.6; focus-visible state-layer
remains keyboard feedback.
```

---

## §10 — Phase 1 Entry Conditions

Phase 1 may start if this report is approved with the following locks:

```txt
1. Ripple v2 remains infrastructure, not component cycle.
2. No <md-ripple> / @material/web runtime import in v3.5.6.
3. data-ax-ripple is the stable declarative contract.
4. v3.5.6 includes both bounded and unbounded modes.
5. Token bridge includes --md-ripple-hover-color and
   --md-ripple-pressed-color aliases.
6. Public JS API includes attach/detach.
7. components.css §0 remains untouched.
8. RIPPLE-V2-AUDIT.md is created as Phase 1 contract doc.
9. Consumer-state promotion proposal:
   - FAB family and Card action become TARGET after implementation/QA
   - future unclosed components remain CANDIDATE
```

Phase 1 deliverable:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

Phase 1 must define:

```txt
data attributes
JS API
CSS tokens
bounded/unbounded behavior
disabled behavior
reduced-motion behavior
forbidden-ancestor policy
keyboard scope decision
consumer responsibilities
provider responsibilities
Phase 2 implementation plan
```

---

## §11 — G1-G5 + G22-G26 Phase 0 Readiness

| Gate | Phase 0 status | Notes |
|---|---|---|
| G1 Validator 1.000 | PASS | Phase 0 docs only; validator remains 1.000. |
| G2 Baseline untouched | PASS | No baseline/public CSS or style-guide edits. |
| G3 Publish runs cleanly | N/A for Phase 0 | No publish-surface mutation. |
| G4 Module artifacts present | CURRENT v1; v2 pending | Existing v1 files inventoried; v2 implementation is Phase 2. |
| G5 CHANGELOG | Future | Phase 5 only. |
| G22 Multi-consumer requirement | PASS | 7 TARGET + 8 CANDIDATE graph. |
| G23 Semantic neutrality | PASS if opt-in contract lands | `data-ax-ripple` improves neutrality over selector allowlist. |
| G24 Boundary rules | PASS if §0 untouched | Static state-layer remains baseline; animated ripple remains provider enhancement. |
| G25 Independent audit doc | TARGET | Phase 1 creates `RIPPLE-V2-AUDIT.md`. |
| G26 Public dependency contract | TARGET | Phase 1 defines stable public contract. |

---

## §12 — Non-Goals

Phase 0 did not:

```txt
edit lab-ripple.css
edit lab-ripple.js
edit lab-ripple-pattern.html
edit components.css
edit style-guide.html
edit tokens.css
edit blocks.css
add data-ax-ripple to specimens
import @material/web
create a custom element wrapper
edit Button/Icon button/Card/FAB docs
edit MODULE-STATUS-MATRIX.md
close BACKLOG #25
close BACKLOG #27
update CURRENT-STATE.md
update NEXT-SESSION.md
```

---

## §13 — One-Line Summary

```txt
v3.5.6 Phase 0 confirms Ripple v2 should be an Axismundi-native,
Material Web contract-aligned infrastructure rewrite: data-ax-ripple
opt-in, bounded + unbounded modes, token bridge, attach/detach API,
and strict preservation of components.css §0 as the static state-layer
foundation.
```

