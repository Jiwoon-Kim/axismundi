# v3.5.6 — Ripple v2 + `data-ax-ripple` — Phase 1 Plan

> **Status**: Plan v1.0 — pending review / approval  
> **Release lane**: Foundation / infrastructure amendment  
> **Backlog scope**: #25 Ripple v2 contract + #27 `data-ax-ripple` opt-in  
> **Phase 0 source**: `docs/v3.5.6/RIPPLE-V2-PHASE-0-REPORT.md`  
> **Mode**: Plan-only. Do not implement Ripple v2 in Phase 1.

---

## §0 — Phase 1 Goal

Phase 1 authors the stable Ripple v2 public dependency contract before implementation.

Deliverable:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

This is an infrastructure single-doc audit. It is not a Component Full-Spec 3-doc trio.

Phase 1 should answer:

```txt
What is the public contract?
What data attributes exist?
What JS API exists?
What CSS custom properties exist?
What are bounded/unbounded semantics?
What remains unchanged from v1?
What must Phase 2 implement?
What must Phase 3 verify?
What must Phase 5 align/close?
```

---

## §1 — Deliverable Shape

### Create exactly one new audit doc

```txt
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

Reason:

```txt
Ripple v2 is infrastructure provider contract work.
The correct shape is one independent infrastructure audit doc,
not SPEC / MEASUREMENT / WP-MAPPING component trio.
```

### Do not edit in Phase 1

```txt
lab-ripple.css
lab-ripple.js
lab-ripple-pattern.html
RIPPLE-AUDIT.md
components.css
style-guide.html
tokens.css
blocks.css
MODULE-STATUS-MATRIX.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
Button/Icon button/Card/FAB SPEC docs
```

Phase 1 is contract authoring only. Phase 2 implements. Phase 5 handles release mechanics and closed-consumer alignment notes.

---

## §2 — Required `RIPPLE-V2-AUDIT.md` Sections

Recommended structure:

```txt
§0  Contract verdict / framing
§1  Authoritative inputs
§2  Current v1 inventory summary
§3  Material Web alignment contract
§4  Public declarative API
§5  Public JS API
§6  CSS token contract
§7  Bounded and unbounded variants
§8  State-layer hierarchy
§9  Accessibility and reduced motion
§10 Forbidden ancestors / WordPress-federation safety
§11 Consumer-state mapping
§12 Migration strategy
§13 Phase 2 implementation requirements
§14 Phase 3 QA requirements
§15 G1-G5 + G22-G26 readiness
§16 Risks and deferred items
§17 References
§18 What this audit does NOT do
```

Section count may shift slightly during execution, but all topics above must appear.

---

## §3 — Contract Locks From Phase 0

Phase 1 must preserve these Phase 0 locks.

### Lock 1 — No Material Web runtime import

```txt
Align with Material Web's ripple contract.
Do not import <md-ripple>.
Do not add @material/web as runtime dependency.
```

Rationale:

```txt
WordPress theme/plugin contexts need Axismundi-native CSS + selective JS.
```

### Lock 2 — `data-ax-ripple` is the stable declarative API

Primary contract:

```html
<button data-ax-ripple>...</button>
<button data-ax-ripple="bounded">...</button>
<button data-ax-ripple="unbounded">...</button>
```

Phase 1 must decide final value semantics:

```txt
empty attribute = bounded
"bounded"       = bounded
"unbounded"     = unbounded
invalid value   = bounded fallback or ignored? (must decide)
```

Preliminary recommendation:

```txt
empty / missing value on present attribute = bounded
unknown value = bounded fallback + console.warn only in debug? optional
```

### Lock 3 — Bounded + unbounded both ship in v3.5.6

Required:

```txt
bounded default
unbounded explicit opt-in
```

Reason:

```txt
Button/Card action = bounded-like
Icon button/FAB = centered circular or unbounded-like
```

### Lock 4 — Token bridge required

Required Material Web-compatible aliases:

```txt
--md-ripple-hover-color
--md-ripple-pressed-color
```

Required Axismundi internal tokens:

```txt
--ax-ripple-hover-color
--ax-ripple-pressed-color
--ax-ripple-hover-opacity
--ax-ripple-pressed-opacity
```

Phase 1 must decide alias direction. Recommendation:

```txt
internal tokens read from Material Web aliases when present
Material Web aliases default to Axismundi/system values
```

### Lock 5 — Public JS API required

Required:

```js
window.axRipple.attach(control, options?)
window.axRipple.detach(control)
```

Phase 1 should consider:

```js
window.axRipple.refresh(root?)
```

Recommendation:

```txt
attach/detach required
refresh optional but useful for WordPress/editor dynamic content
```

### Lock 6 — `components.css §0` immutable

Phase 1 must repeat:

```txt
components.css §0 static state-layer foundation remains CURRENT.
Ripple v2 is animated progressive enhancement above it.
Ripple v2 does not replace hover/focus/pressed CSS state-layer.
```

---

## §4 — Open Decisions Phase 1 Must Settle

### Decision A — Keyboard ripple scope

Options:

```txt
A. pointer-only ripple; keyboard uses §0 focus-visible state-layer
B. keyboard activation spawns centered ripple on Enter/Space
```

Recommendation:

```txt
A for v3.5.6.
Reason: preserve native focus-visible feedback; avoid extra keyboard
handler complexity in first v2 contract. Record B as future candidate.
```

Phase 1 must not leave this ambiguous.

### Decision B — `data-ax-ripple-for`

Options:

```txt
A. include in v3.5.6 contract
B. document as future, ship attach(control) for programmatic targeting only
```

Recommendation:

```txt
B for v3.5.6.
Reason: parent-host opt-in + imperative attach/detach cover the immediate
WordPress/editor use cases. Referenced target markup adds extra ID lifecycle
complexity and no current closed Wave 1 consumer requires it.
```

### Decision C — Transitional allowlist behavior

Options:

```txt
A. hard switch: only [data-ax-ripple]
B. dual mode: [data-ax-ripple] plus legacy HOST_SELECTOR during migration
```

Recommendation:

```txt
B in implementation, A in public docs.
Stable public contract is [data-ax-ripple].
Legacy allowlist is migration compatibility and should be documented as
temporary/internal.
```

### Decision D — Consumer-state promotions

Recommendation:

```txt
Promote closed Wave 1 action surfaces after implementation/QA:
  FAB #3 + Extended FAB #4 -> TARGET
  Card #9 action/interactive surface -> TARGET

Keep future/unclosed surfaces CANDIDATE:
  FAB menu #5
  Button group #6
  Split button #7
  Toolbar #8
  App bar #11 action slots
  List #33 item hover/action surface
```

Phase 1 should write this as intended Phase 5 matrix amendment, not as current fact.

---

## §5 — Consumer Mapping Table Required

`RIPPLE-V2-AUDIT.md` must include a table with at least:

| Consumer | v3.5.4 state | v3.5.6 intended state | Mode | Notes |
|---|---|---|---|---|
| Button #1 | TARGET | TARGET | bounded | closed Wave 1 |
| Icon button #2 | TARGET | TARGET | unbounded | icon-only circular surface |
| Chip #24 | TARGET | TARGET | bounded | legacy audit remains unedited unless specimens change |
| Menu #15 | TARGET | TARGET | bounded | existing allowlist |
| Nav bar #12 | TARGET | TARGET | unbounded or bounded? | Phase 1 must decide |
| Nav rail #13 | TARGET | TARGET | unbounded or bounded? | Phase 1 must decide |
| Tabs #14 | TARGET | TARGET | bounded | existing allowlist |
| FAB #3 + Extended FAB #4 | CANDIDATE | TARGET after v3.5.6 | unbounded | closed Wave 1, newly promotable |
| Card #9 action | CANDIDATE | TARGET after v3.5.6 | bounded | base card remains NONE |
| Future components | CANDIDATE | CANDIDATE | TBD | await own audits |

Important:

```txt
Nav bar / Nav rail mode may be nuanced.
Phase 1 must not hand-wave them; choose bounded/unbounded or mark
implementation-specific with a reason.
```

---

## §6 — Accessibility / Motion Contract

Phase 1 audit must lock:

```txt
ripple DOM nodes aria-hidden="true"
ripple DOM nodes not focusable
ripple does not alter accessible names
ripple does not add live regions
ripple does not replace focus-visible styling
disabled / aria-disabled hosts do not ripple
forbidden ancestors still bail out
prefers-reduced-motion respected
```

Reduced motion contract:

```txt
No radial expansion under prefers-reduced-motion: reduce.
Allow a brief static opacity/tint acknowledgement if visually acceptable.
Cleanup still occurs.
```

No-JS contract:

```txt
Without lab-ripple.js, consumers retain components.css §0 state-layer
feedback and native interaction semantics.
```

---

## §7 — Phase 2 Readiness Criteria

Phase 1 is ready to hand off to Phase 2 only when `RIPPLE-V2-AUDIT.md` includes:

```txt
1. final data attribute API
2. final JS API
3. final token list and alias direction
4. bounded/unbounded geometry expectations
5. keyboard decision
6. disabled/reduced-motion/forbidden-ancestor behavior
7. consumer mapping table
8. migration strategy
9. implementation file scope
10. QA checklist
```

Expected Phase 2 files:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple-pattern.html
```

Expected Phase 2 non-goals:

```txt
components.css §0
style-guide.html
closed consumer SPEC alignment notes
MODULE-STATUS-MATRIX.md
BACKLOG #25/#27 closure
CHANGELOG/ROADMAP
```

---

## §8 — Reference Templates

Use:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-AUDIT.md
products/reference-implementations/axismundi-lab/modules/popover/docs/POPOVER-AUDIT.md
docs/v3.5.6/RIPPLE-V2-PHASE-0-REPORT.md
```

Reference roles:

```txt
RIPPLE-AUDIT.md:
  current v1 behavior and Beer-CSS lineage

POPOVER-AUDIT.md:
  single-doc lab runtime audit precedent

RIPPLE-V2-PHASE-0-REPORT.md:
  v2 contract locks and risk dispositions
```

Component SPEC docs are reference only for consumer alignment and should not shape the doc into a 3-doc component trio.

---

## §9 — Risks

### Risk 1 — Doc becomes implementation plan too early

Mitigation:

```txt
Phase 1 defines contract and readiness.
Implementation details belong to Phase 2, except where needed to make
the contract testable.
```

### Risk 2 — Bounded/unbounded ambiguity leaks into Phase 2

Mitigation:

```txt
Phase 1 must explicitly map each target consumer to bounded/unbounded
or justify implementation-specific handling.
```

### Risk 3 — Token aliases create circular defaults

Mitigation:

```txt
Phase 1 must specify alias direction clearly.
Do not define --ax-* in terms of --md-* and --md-* in terms of --ax-*
without a fallback break.
```

### Risk 4 — Keyboard ripple gets silently inherited from pointer code

Mitigation:

```txt
Phase 1 must decide keyboard scope.
If pointer-only, state it plainly and explain focus-visible fallback.
```

### Risk 5 — Legacy allowlist becomes hidden public API

Mitigation:

```txt
Document legacy allowlist as transitional implementation compatibility,
not stable public contract.
```

### Risk 6 — Consumer alignment side-edits creep into Phase 1

Mitigation:

```txt
No Button/Icon button/Card/FAB SPEC edits in Phase 1.
Alignment notes are Phase 5 only.
```

---

## §10 — Validation Plan

Phase 1 plan validation:

```txt
new file only:
  docs/v3.5.6/RIPPLE-V2-PHASE-1-PLAN.md

no ripple implementation edits
no baseline edits
no state/handoff edits
validator 1.000 PASS
```

Phase 1 execution validation:

```txt
new file:
  products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md

contains all contract locks
contains consumer mapping table
contains G1-G5 + G22-G26 readiness
contains Phase 2 file scope
contains non-goals
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

## §11 — Non-Goals

This plan does not:

```txt
create RIPPLE-V2-AUDIT.md
edit RIPPLE-AUDIT.md
edit lab-ripple.css
edit lab-ripple.js
edit lab-ripple-pattern.html
edit components.css
edit style-guide.html
edit tokens.css
edit blocks.css
edit Button/Icon button/Card/FAB SPEC docs
edit MODULE-STATUS-MATRIX.md
edit BACKLOG.md
edit CHANGELOG.md
edit ROADMAP.md
update CURRENT-STATE.md
update NEXT-SESSION.md
import @material/web
implement <md-ripple>
```

---

## §12 — Approval Gate

Plan v1.0 requires review before Phase 1 execution.

Approval routes:

```txt
approve as-is
approve with findings folded into execution
request Plan v1.1
defer Ripple v2 Phase 1
```

If approved, next Codex task:

```txt
Create products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md.
Documentation-only.
Do not implement lab-ripple v2 yet.
```

