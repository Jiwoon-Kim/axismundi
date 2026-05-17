# v3.5.6 — Ripple v2 + `data-ax-ripple` — Phase 0 Plan

> **Status**: Plan v1.0 — pending review / approval  
> **Release lane**: Foundation / infrastructure amendment  
> **Backlog scope**: #25 Ripple v2 contract + #27 `data-ax-ripple` opt-in  
> **Date**: 2026-05-16  
> **Mode**: Plan-only. Do not implement Ripple v2 in Phase 0.

---

## §0 — Framing

v3.5.6 is not a Wave 1 component cycle. It is an infrastructure module rewrite / amendment cycle for row #36 `ripple/`.

```txt
Previous Wave 1 closures:
  v3.5.1 Button #1
  v3.5.2 Icon button #2
  v3.5.3 Card #9
  v3.5.5 FAB #3 + Extended FAB #4

Foundation cleanup already done:
  v3.5.4 Matrix Consumer-State Amendment

This release:
  v3.5.6 Ripple v2 contract + data-ax-ripple opt-in
```

The central goal is to convert ripple from the current Beer-CSS-derived, allowlist-heavy lab experiment into an Axismundi-native, Material Web-aligned public dependency contract.

This does **not** mean importing `<md-ripple>` directly. Phase 0 must preserve the earlier Button #1 finding:

```txt
Material Web contract alignment: yes.
Direct Material Web custom-element dependency: not without separate decision.
Axismundi implementation: CSS + selective JS, WordPress-compatible.
```

---

## §1 — Authoritative Inputs To Read

### Canonical Axismundi docs

```txt
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.1/BUTTON-PHASE-0-REPORT.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

Why:

```txt
CHARTER: public/lab/baseline/plugin boundaries, WordPress compatibility.
PROMOTION: G1-G5 universal + G22-G26 infrastructure gates.
MATRIX: row #36 consumer-state buckets after v3.5.4.
BUTTON Phase 0: original Material Web ripple alignment finding.
GROUNDING: process discipline + v3.5.6 risk map.
```

### Current ripple module

```txt
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-AUDIT.md
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple-pattern.html
```

Phase 0 must inventory:

```txt
current HOST_SELECTOR allowlist
current delegated pointerdown model
current CSS animation/state-layer model
current pattern specimens
current audit claims vs actual implementation
```

### Closed consumer audit docs

```txt
products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/card/docs/CARD-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/fab/docs/FAB-SPEC-AUDIT.md
```

Why:

```txt
These are the closed Wave 1 consumers that will need v3.5.6 alignment
notes at Phase 5 if Ripple v2 changes their consumer-state contract.
```

### External spec input

Material Web Ripple:

```txt
https://material-web.dev/components/ripple/
```

Phase 0 must cite, summarize, and compare:

```txt
ripple = visual state layer for hover/press
attachment modes: parent element / referenced element / imperative attach()
bounded and unbounded ripple support
position: relative container requirement
accessibility: visual component, no AT requirements
tokens: --md-ripple-hover-color / --md-ripple-pressed-color
API: disabled, htmlFor, control, attach(control), detach()
```

---

## §2 — Scope Locks

### In scope

```txt
Phase 0 plan
Phase 0 report
Infrastructure qualification re-verification
Material Web contract comparison
Current ripple module inventory
data-ax-ripple opt-in contract design questions
consumer-state re-evaluation questions
closed Wave 1 consumer alignment strategy
Phase 1/2/3/5 shape proposal
risk register
```

### Out of scope for Phase 0

```txt
editing lab-ripple.css
editing lab-ripple.js
editing lab-ripple-pattern.html
editing components.css §0 state-layer foundation
editing style-guide.html
editing closed consumer SPEC docs
editing MODULE-STATUS-MATRIX.md
closing BACKLOG #25 or #27
adding data-ax-ripple to specimens
importing @material/web ripple
creating a custom element dependency
```

Phase 0 is documentation-only. Implementation starts no earlier than Phase 2 after Phase 1 contract approval.

---

## §3 — Infrastructure Gate Applicability

Ripple v2 is an infrastructure module cycle.

### Applicable gates

```txt
G1  Validator 1.000
G2  Baseline untouched
G3  Publish runs cleanly / publish impact recorded
G4  Module artifacts present
G5  CHANGELOG / release close

G22 Infrastructure qualification
G23 Consumer graph declaration
G24 No single-component infrastructure
G25 Stable public API contract
G26 Provider / consumer separation
```

### Not applicable

```txt
G6-G10 Component Full-Spec gates
G11-G16 Interaction Runtime component gates
G17-G21 Baseline-only Record gates
```

Reason:

```txt
ripple/ is a cross-cutting Interaction Runtime Infrastructure provider,
not a component surface and not a single interaction component like
snackbar/tooltip/popover.
```

Phase 0 must be careful here: ripple is interaction runtime infrastructure, but the applicable framework gate family is infrastructure (G22-G26), not the component Interaction Runtime gates (G11-G16).

---

## §4 — Infrastructure Qualification Re-Verification

Phase 0 must re-run the infrastructure qualification logic from `PROMOTION-CRITERIA.md §5.1`.

Expected preliminary answer:

```txt
multi-consumer requirement:
  PASS. v3.5.4 matrix records 7 TARGET consumers and 8 CANDIDATE
  consumers, far above the threshold for infrastructure.

semantic neutrality:
  MUST VERIFY. v2 must be a generic state-layer interaction provider,
  not a Button-specific or FAB-specific helper.

independent audit doc:
  CURRENT. Existing ripple/RIPPLE-AUDIT.md exists, but Phase 1 likely
  needs a v2 amendment audit doc or replacement section.

stable public dependency contract:
  TARGET OF v3.5.6. The entire point of v2 is to define this contract.
```

Phase 0 report should explicitly answer:

```txt
Does Ripple v2 remain infrastructure after moving from allowlist to opt-in?
Does data-ax-ripple strengthen or weaken provider/consumer separation?
Which API names become stable public contract?
```

---

## §5 — Material Web Alignment Axes

Phase 0 must compare Axismundi v2 candidate design against Material Web across five axes.

### Axis 1 — Attachment model

Material Web supports:

```txt
1. ripple element inside parent
2. ripple attached to referenced element
3. ripple attached imperatively via attach(control)
```

Axismundi candidate:

```txt
data-ax-ripple                     declarative host opt-in
data-ax-ripple-for                 optional referenced target, if approved
window.axRipple.attach(control)    imperative attach, if approved
window.axRipple.detach(control)    imperative detach, if approved
```

Phase 0 decision question:

```txt
Does v3.5.6 ship all three equivalent modes, or only data-ax-ripple +
imperative attach/detach?
```

### Axis 2 — Bounded vs unbounded

Material Web documents bounded and unbounded ripple.

Phase 0 decision question:

```txt
Option A: v3.5.6 bounded only; unbounded deferred.
Option B: v3.5.6 bounded + unbounded together.
```

Preliminary lean:

```txt
Bounded should ship in v3.5.6.
Unbounded should be evaluated carefully because Icon button, FAB, Nav bar,
and Nav rail are likely unbounded-like consumers.
```

Do not silently defer unbounded if it would make Icon button/FAB alignment false.

### Axis 3 — Token bridge

Material Web tokens:

```txt
--md-ripple-hover-color
--md-ripple-pressed-color
```

Axismundi candidate:

```txt
Support Material Web token aliases.
Map them to existing Axismundi state-layer tokens where possible.
Do not create a second untracked color system.
```

Phase 0 must inventory current state-layer token names before proposing final aliases.

### Axis 4 — Accessibility

Material Web states ripples are visual components with no assistive technology requirements.

Axismundi implication:

```txt
ripple must not create focusable nodes
ripple must not change accessible names
ripple must not create aria-live output
ripple must respect reduced-motion policy
ripple must not replace native focus indicators
```

### Axis 5 — WordPress compatibility

Axismundi must not assume:

```txt
custom elements are safe in all WordPress rendering contexts
block serialization will preserve unknown element semantics everywhere
theme authors want @material/web runtime dependency
```

Phase 0 must reaffirm:

```txt
contract-compatible, implementation-native
```

---

## §6 — Current Ripple Inventory Questions

Phase 0 report must inspect and answer:

```txt
1. What exactly is the current HOST_SELECTOR allowlist?
2. Does it still match the v3.5.4 TARGET bucket?
3. Does lab-ripple.js use document-level delegation only?
4. Does it support detach/dispose?
5. Does it support disabled hosts?
6. Does it support keyboard activation or only pointer interactions?
7. Does it respect prefers-reduced-motion?
8. Does lab-ripple.css expose token hooks?
9. Does lab-ripple-pattern.html show all current target consumers?
10. Which claims in RIPPLE-AUDIT.md are stale after v3.5.4?
```

Expected Phase 0 output:

```txt
current model summary
gap table
v2 amendment candidates
Phase 1 contract questions
```

---

## §7 — Consumer-State Re-Evaluation

Current v3.5.4 matrix:

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

Phase 0 must decide whether the v2 contract should:

```txt
Option A: preserve TARGET/CANDIDATE buckets exactly, only improving API.
Option B: promote some CANDIDATE consumers to TARGET.
Option C: collapse all action-like CANDIDATE consumers into TARGET.
```

Preliminary lean:

```txt
Option B, conservative promotion:
  likely promote closed Wave 1 action consumers already audited:
    FAB #3 + Extended FAB #4
    Card #9 action/interactive surface
  keep future/unclosed components as CANDIDATE until their own audits:
    FAB menu, Button group, Split button, Toolbar, App bar, List
```

Phase 0 report must not silently rewrite the matrix. It should propose the target bucket change; actual matrix edit belongs to Phase 5 if approved.

---

## §8 — Closed Consumer Alignment Strategy

If Phase 2 lands a new Ripple v2 public contract, Phase 5 should add short alignment notes to:

```txt
BUTTON-SPEC-AUDIT.md
ICON-BUTTON-SPEC-AUDIT.md
CARD-SPEC-AUDIT.md
FAB-SPEC-AUDIT.md
```

Pattern:

```txt
v3.5.6 Ripple v2 alignment note
  - consumer-state after v3.5.6
  - whether data-ax-ripple is used by that component's lab pattern
  - whether baseline remains unchanged
  - whether animated ripple is progressive enhancement above CSS state-layer
```

This follows the v3.5.4 matrix alignment-note pattern and is intentionally minimal.

Phase 0 must also decide whether Chip v3.4.9 remains legacy:

```txt
Option A: matrix carries Chip ripple TARGET state; audit doc remains unedited.
Option B: add a legacy alignment note to Chip audit docs too.
```

Preliminary lean:

```txt
Option A unless Phase 2 actually edits Chip specimens.
```

---

## §9 — Phase 1 / 2 / 3 / 5 Shape

### Phase 1 — Contract audit

Likely deliverable:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

or a major v2 section inside existing:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-AUDIT.md
```

Phase 0 must recommend one.

Preliminary lean:

```txt
Create RIPPLE-V2-AUDIT.md, keep RIPPLE-AUDIT.md as historical v1/current audit.
```

Phase 1 should define:

```txt
stable public API
data attributes
JS API
CSS custom properties
bounded/unbounded support
disabled/reduced-motion behavior
consumer responsibilities
provider responsibilities
migration strategy
```

### Phase 2 — Implementation

Likely deliverables:

```txt
lab-ripple.css
lab-ripple.js
lab-ripple-pattern.html
```

Potential supporting docs:

```txt
RIPPLE-V2-AUDIT.md implementation verdict update
```

Phase 2 must not touch:

```txt
components.css §0 state-layer foundation
style-guide.html
baseline component CSS
```

### Phase 3 — Visual / behavioral QA

Must verify:

```txt
hover state
press state
keyboard activation if supported
disabled host
reduced motion
bounded container clipping
unbounded behavior if included
data-ax-ripple opt-in
imperative attach/detach if included
no accessible-name/focus/aria regressions
```

### Phase 5 — Mechanical close

Likely edits:

```txt
CHANGELOG.md
ROADMAP.md
BACKLOG.md (#25 + #27 close if implementation lands)
MODULE-STATUS-MATRIX.md row #36 v3.5.6 amendment notice
4 closed Wave 1 consumer SPEC alignment notes
```

Do not update `NEXT-SESSION.md` until a true session boundary.

---

## §10 — Risks To Surface In Phase 0 Report

### Risk A — Custom element dependency drift

```txt
Material Web uses <md-ripple>.
Axismundi must avoid accidentally making @material/web a runtime dependency
without a separate WordPress compatibility decision.
```

Disposition target:

```txt
Contract-compatible; implementation-native.
```

### Risk B — Bounded/unbounded scope creep

```txt
Button/Card action surfaces are bounded-like.
Icon button/FAB/Nav surfaces may require unbounded-like behavior.
```

Disposition target:

```txt
Phase 0 must decide whether v3.5.6 includes unbounded or explicitly
defers it with honest consumer-state consequences.
```

### Risk C — Allowlist to opt-in migration

```txt
Current lab-ripple.js likely relies on HOST_SELECTOR allowlist.
data-ax-ripple flips the contract to declarative opt-in.
```

Disposition target:

```txt
Decide hard switch vs transitional dual mode.
Preliminary lean: dual-mode only inside lab-ripple-pattern for migration
evidence; stable public contract should be opt-in.
```

### Risk D — Closed consumer side-edits

```txt
Button/Icon button/Card/FAB are already closed releases.
Ripple v2 will require short alignment notes.
```

Disposition target:

```txt
Use v3.5.4 matrix alignment-note precedent; keep side-edits minimal.
```

### Risk E — State-layer hierarchy confusion

```txt
components.css §0 static state-layer foundation remains CURRENT.
Animated ripple is progressive enhancement above that foundation.
```

Disposition target:

```txt
Do not replace or mutate §0 baseline state-layer in v3.5.6.
```

### Risk F — Current ripple audit staleness

```txt
RIPPLE-AUDIT.md predates consumer-state vocabulary and v3.5.4 matrix buckets.
```

Disposition target:

```txt
Create v2 audit or amendment section that clearly distinguishes:
  current v1 implementation
  v2 target contract
  future/non-goal items
```

---

## §11 — Validation Plan

Phase 0 plan validation:

```txt
documentation-only
new file: docs/v3.5.6/RIPPLE-V2-PHASE-0-PLAN.md
no lab-ripple edits
no baseline edits
no state/handoff edits
validator 1.000 PASS
```

Phase 0 report validation:

```txt
Material Web citation included
current ripple file inventory complete
G1-G5 + G22-G26 applicability correct
consumer-state matrix snapshot correct
risks A-F included
Phase 1 entry conditions explicit
validator 1.000 PASS
```

Command:

```powershell
python .\tools\validators\validate_theme_pilot.py
```

Expected:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

---

## §12 — Non-Goals

This plan does not:

```txt
implement Ripple v2
edit lab-ripple.css
edit lab-ripple.js
edit lab-ripple-pattern.html
edit components.css
edit style-guide.html
edit tokens.css
edit blocks.css
import @material/web
create a custom element wrapper
change Button/Icon button/Card/FAB artifacts
change MODULE-STATUS-MATRIX.md
close BACKLOG #25
close BACKLOG #27
update CURRENT-STATE.md
update NEXT-SESSION.md
```

---

## §13 — Approval Gate

Plan v1.0 requires review before Phase 0 report execution.

Approval routes:

```txt
approve as-is
approve with P1/P2/P3 findings folded into Phase 0 report
request Plan v1.1
defer v3.5.6 and choose another release lane
```

If approved, next Codex task:

```txt
Create docs/v3.5.6/RIPPLE-V2-PHASE-0-REPORT.md.
Documentation-only.
No ripple implementation yet.
```

