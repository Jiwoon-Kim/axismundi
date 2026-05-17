# Axismundi v3.5.5 — FAB Family Phase 2 Plan

> **Status**: PLAN-ONLY v1.0. Awaiting review/approval before Phase 2 execution.  
> **Date**: 2026-05-16  
> **Source authority**: `docs/v3.5.5/FAB-PHASE-0-REPORT.md` + FAB Phase 1 audit trio.  
> **Reference template**: Button v3.5.1, Icon button v3.5.2, and Card v3.5.3 Phase 2 plans.  
> **Pre-entry decision**: FAB + Extended FAB ripple = CANDIDATE; no ripple wiring in v3.5.5.

---

## §0 — Plan Scope And Gate

This is a plan-only artifact. It does not create Phase 2 deliverables.

Purpose:

```txt
1. Define exact Phase 2 deliverables.
2. Lock selector policy and non-goals.
3. Define the FAB family pattern HTML section structure.
4. Keep floating/positioning behavior out of primitive implementation.
5. Map validation and G1-G10 gate readiness.
```

Approval gate:

```txt
Phase 2 execution does not begin until User approves this plan.
```

---

## §1 — Lock Decisions

### §1.1 — Deliverables

Phase 2 creates exactly two deliverable artifacts:

| File | Path | Role |
|---|---|---|
| `lab-fab.css` | `products/reference-implementations/axismundi-lab/modules/fab/lab-fab.css` | Lab-internal demo scaffolding and visualization comments on top of baseline `.ax-fab` / `.ax-fab-extended`. |
| `lab-fab-pattern.html` | `products/reference-implementations/axismundi-lab/modules/fab/lab-fab-pattern.html` | Full family pattern page for FAB + Extended FAB static primitives. |

Not created:

```txt
lab-fab.js
```

Reason:

```txt
FAB Phase 2 has no runtime behavior. Ripple is CANDIDATE, not TARGET,
and Extended FAB collapse/expand is deferred.
```

Phase bookkeeping:

```txt
Optional after successful execution:
  FAB-SPEC-AUDIT.md §11 criterion #3 (Pattern HTML completeness) -> PASS

Do not update CURRENT-STATE.md unless the user asks for a phase-boundary
snapshot. Do not update NEXT-SESSION.md unless ending or handing off the
session.
```

### §1.2 — Selector Policy

Decision:

```txt
Use .lab-fab-demo as the lab page scope marker.
```

Allowed:

```txt
.lab-fab-demo layout scaffolding
.lab-fab-demo .ax-fab[...] visualization-only helpers
.lab-fab-demo .ax-fab-extended[...] visualization-only helpers
module-private --lab-fab-* layout variables
comments declaring dependency profile and audit references
```

Forbidden:

```txt
unscoped .ax-fab overrides
unscoped .ax-fab-extended overrides
new .ax-fab size variants
new .ax-fab-extended size variants
baseline color/elevation/radius overrides
global fixed/sticky positioning utilities
ripple wiring
JavaScript dependencies
```

Scoped visualization helpers, if used, must be limited to:

```txt
demo grid/layout
caption affordance
state explanation
optional hover/elevation teaching wrapper
```

They must not change baseline component measurements.

### §1.3 — Elevation Demo Boundary

FAB has elevation semantics:

```txt
rest:  level3
hover: level4
focus/active: level3
disabled: level0
```

Allowed:

```txt
Document the level3 -> level4 hover relationship.
Use captions or demo wrappers to explain it.
```

Forbidden:

```txt
introducing new elevation tokens
overriding .ax-fab:hover globally
creating alternate elevation variants
```

### §1.4 — Positioning Boundary

Decision:

```txt
Phase 2 pattern page uses inline catalog specimens only.
```

Do not create a production fixed/sticky FAB layout in v3.5.5.

Rationale:

```txt
The baseline says the component itself is inline-block/inline-flex and
page-level positioning is the author's job. Positioning belongs to theme,
template, pattern, or plugin integration, not the primitive.
```

Rejected for Phase 2:

```txt
fixed bottom-end FAB demo as production pattern
sticky FAB utility
responsive hide/show positioning behavior
toolbar-attached FAB composition
```

Optional note allowed:

```txt
A short explanatory caption may say that real placement is integration-owned.
```

### §1.5 — Static Catalog Caption Rule

Any specimen that looks interactive but has no wired behavior must say so.

Required caption language for behavior-looking sections:

```txt
Static catalog specimen — no runtime action or transition is wired here.
Production usage wires behavior at the integrator level (theme JS / block
editor / plugin).

정적 카탈로그 specimen — 여기에는 런타임 액션이나 전환 동작이 배선되어
있지 않다. 실제 사용 시 theme JS / block editor / plugin 레벨에서 동작을
배선한다.
```

Specific cases:

```txt
Extended FAB static label-bearing examples
elevation hover explanation
aria-disabled plugin-managed examples
placement boundary note
```

### §1.6 — Icon Slot Canonical Pattern

Decision:

```txt
Use icon-system-forward Material Symbols snippets.
```

FAB:

```html
<span class="material-symbols-rounded notranslate ax-fab-icon"
      translate="no"
      aria-hidden="true"
      draggable="false">add</span>
```

Extended FAB:

```html
<span class="material-symbols-rounded notranslate ax-fab-extended__icon"
      translate="no"
      aria-hidden="true"
      draggable="false">add</span>
<span class="ax-fab-extended__label">Create</span>
```

Do not copy current public inline SVG snippets into the new lab pattern page except as escaped anti-pattern/reference text if needed.

### §1.7 — Disabled Split

Phase 2 pattern HTML must split:

```txt
§5  Disabled — native disabled
§5a Disabled — aria-disabled plugin-managed
```

Caption requirement:

```txt
Native disabled blocks activation by platform behavior.
aria-disabled only communicates state; integrator code must block action.
```

### §1.8 — Ripple / JS Boundary

Decision:

```txt
No ripple wiring.
No lab-fab.js.
```

Reason:

```txt
ripple/ = CANDIDATE for FAB family. Ripple v2 (#25) and data-ax-ripple
opt-in (#27) are the venue for future promotion.
```

Forbidden:

```txt
data-ax-ripple
<md-ripple>
lab-ripple.js attach calls
pointerdown animation code
```

---

## §2 — Deliverable Artifact Detail

### §2.1 `lab-fab.css`

Expected role:

```txt
lab-internal page scaffolding
demo grid/section layout
caption styles
optional scoped helper for visual explanation only
```

Expected size:

```txt
80-150 lines
```

Required header notes:

```txt
Source authority:
  FAB-SPEC-AUDIT.md
  FAB-MEASUREMENT-AUDIT.md
  FAB-WP-MAPPING.md

Dependency profile:
  state-layer foundation = CURRENT
  icon-system/ = CURRENT unconditional
  ripple/ = CANDIDATE
  elevation tokens = CURRENT token graph
```

Forbidden CSS patterns:

```txt
.ax-fab { ... }
.ax-fab-extended { ... }
.ax-fab:hover { ... }
.ax-fab-extended:hover { ... }
.has-state-layer { ... }
```

Allowed scoped examples:

```css
.lab-fab-demo .lab-fab-grid { ... }
.lab-fab-demo .lab-fab-caption { ... }
.lab-fab-demo .lab-fab-example { ... }
```

Only use `.lab-fab-demo .ax-fab[...]` or `.lab-fab-demo .ax-fab-extended[...]` if the declaration is visualization-only and does not alter component measurements.

### §2.2 `lab-fab-pattern.html`

Required page sections:

```txt
§1 Overview / dependency banner
§2 FAB sizes
    56px default
    80px medium
    96px large

§3 FAB surface variants
    tonal primary default
    tonal secondary
    tonal tertiary
    primary
    secondary
    tertiary

§4 Extended FAB static
    icon + visible label
    English + Korean specimen
    label visibility explicit
    static catalog caption

§5 Disabled — native
    FAB disabled
    Extended FAB disabled

§5a Disabled — aria-disabled plugin-managed
    FAB aria-disabled
    Extended FAB aria-disabled
    integrator guard caption

§6 State-layer opt-out / ripple boundary
    has-state-layer or static-state explanation if applicable
    no data-ax-ripple
    no animated ripple

§7 Code snippets
    canonical FAB
    canonical Extended FAB
    disabled split
    aria-disabled plugin-managed

§8 Cross-references
    SPEC / MEASUREMENT / WP-MAPPING
    Phase 0 report
    Phase 1 plan
```

All live controls should use:

```txt
<button type="button">
```

Anti-pattern examples, if included, must be escaped code snippets, not live controls.

---

## §3 — Pattern HTML Acceptance Criteria

The pattern page must include:

```txt
- 3 FAB size specimens.
- 6 FAB surface specimens.
- At least 2 Extended FAB static specimens.
- Native disabled FAB and Extended FAB.
- aria-disabled plugin-managed FAB and Extended FAB.
- Material Symbols / icon-system-forward glyphs.
- Accessible names for all icon-only FABs.
- Visible labels for all Extended FABs.
- Static catalog captions for behavior-looking examples.
- Explicit note that placement is integration-owned.
- Explicit note that ripple remains CANDIDATE and unwired.
```

The pattern page must not include:

```txt
- inline SVG target snippets as live controls.
- div/span fake buttons.
- anchors styled as action FABs.
- fixed/sticky production FAB placement.
- FAB menu.
- Toolbar integration.
- JS imports.
- data-ax-ripple.
```

---

## §4 — G1-G10 Readiness

| Gate | Phase 2 target | Notes |
|---|---|---|
| G1 validator | maintain | Run after artifacts. |
| G2 baseline untouched | maintain | No baseline/public edits. |
| G3 publish cleanly | not required | No publish-surface work in Phase 2. |
| G4 module artifacts | achieve | `lab-fab.css` + `lab-fab-pattern.html`. |
| G5 CHANGELOG | future | Phase 5 only. |
| G6 visual QA | future | Phase 3 after execution. |
| G7 Principle 1 | achieve | Live controls are real `<button>`. |
| G8 Principle 2 | achieve | Native button semantics. |
| G9 WCAG SC accuracy | already achieved | MEASUREMENT records SC 2.5.8 AA and SC 2.5.5 AAA against current target sizes. |
| G10 3-doc audit pattern | already achieved | Phase 1 complete. |

---

## §5 — Validation Commands After Execution

```powershell
# Exactly two deliverable artifacts
Test-Path products\reference-implementations\axismundi-lab\modules\fab\lab-fab.css
Test-Path products\reference-implementations\axismundi-lab\modules\fab\lab-fab-pattern.html
Test-Path products\reference-implementations\axismundi-lab\modules\fab\lab-fab.js

# No unscoped component overrides
Select-String products\reference-implementations\axismundi-lab\modules\fab\lab-fab.css -Pattern '^\s*\.ax-fab\b|^\s*\.ax-fab-extended\b'

# No ripple wiring
Select-String products\reference-implementations\axismundi-lab\modules\fab\lab-fab-pattern.html -Pattern 'data-ax-ripple|md-ripple|lab-ripple'

# Native semantics
Select-String products\reference-implementations\axismundi-lab\modules\fab\lab-fab-pattern.html -Pattern '<button'
Select-String products\reference-implementations\axismundi-lab\modules\fab\lab-fab-pattern.html -Pattern 'role="button"|<div class="ax-fab|<span class="ax-fab|<a class="ax-fab'

# Validator
python .\tools\validators\validate_theme_pilot.py
```

Expected:

```txt
validator 1.000 / 1.000 / 1.000 / 1.000 PASS
lab-fab.js absent
no unscoped .ax-fab or .ax-fab-extended overrides
no ripple wiring
baseline mtimes unchanged
```

---

## §6 — Phase Bookkeeping

Optional after successful execution:

```txt
Update FAB-SPEC-AUDIT.md §11 criterion #3:
  TBD at Phase 2 -> PASS
```

Do not update:

```txt
CURRENT-STATE.md
NEXT-SESSION.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md
MODULE-STATUS-MATRIX.md
```

Reason:

```txt
Phase 2 execution is not release close and not a session boundary.
```

---

## §7 — Explicit Non-Goals

Phase 2 does not:

```txt
- edit components.css
- edit style-guide.html
- edit tokens.css
- edit blocks.css
- edit theme.json
- create lab-fab.js
- wire ripple
- add data-ax-ripple
- import or depend on <md-ripple>
- implement Ripple v2
- create FAB menu
- implement Toolbar integration
- implement Extended FAB collapse/expand
- implement auto-hide on scroll
- implement modal/sheet morph behavior
- create global fixed/sticky placement utility
- register WordPress block styles
- edit PHP
- edit plugin code
- replace public inline SVG specimens
- add new M3 system tokens
- add new FAB sizes
- add new Extended FAB sizes
- edit CURRENT-STATE.md
- edit NEXT-SESSION.md
- edit CHANGELOG.md
- edit ROADMAP.md
- edit BACKLOG.md
```

---

## §8 — Risks

### Risk A — Unscoped Component Overrides

`lab-fab.css` could accidentally become a shadow baseline.

Mitigation:

```txt
Only use .lab-fab-demo scoped layout selectors.
No unscoped .ax-fab / .ax-fab-extended rules.
```

### Risk B — Floating Placement Scope Creep

FAB is conceptually floating, so Phase 2 could accidentally add production placement utilities.

Mitigation:

```txt
Inline catalog specimens only.
Placement is integration-owned and documented as such.
```

### Risk C — Ripple Promotion Drift

Action-surface intuition could lead to adding `data-ax-ripple`.

Mitigation:

```txt
No ripple wiring. ripple/ remains CANDIDATE.
```

### Risk D — Icon-System Contract Hidden By Legacy SVG

The public style guide still uses inline SVG.

Mitigation:

```txt
Use Material Symbols / icon-system-forward live specimens in lab pattern.
Do not edit public style-guide specimens in Phase 2.
```

### Risk E — Extended FAB Behavior Creep

Static Extended FAB could drift into collapse/expand behavior.

Mitigation:

```txt
Static catalog only. Behavior-heavy patterns deferred.
```

### Risk F — Aria-Disabled Misrepresented

aria-disabled specimens could appear truly disabled without runtime guard.

Mitigation:

```txt
Caption plugin-managed guard requirement in both English and Korean.
```

---

## §9 — Approval Gate

Phase 2 execution is blocked until this plan is approved.

Approval should confirm:

```txt
- exactly two deliverables
- no lab-fab.js
- no ripple wiring
- inline catalog specimens only
- no production fixed/sticky FAB placement
- icon-system-forward live specimens
- Pattern A disabled split
- SPEC §11 criterion #3 bookkeeping only
- no CURRENT-STATE.md / NEXT-SESSION.md changes
```

After approval:

```txt
Codex may create lab-fab.css and lab-fab-pattern.html, then update
FAB-SPEC-AUDIT.md §11 criterion #3 to PASS if validation succeeds.
```

---

## §10 — One-Line Summary

FAB Phase 2 will create exactly `lab-fab.css` and `lab-fab-pattern.html`, using scoped lab-only scaffolding and icon-system-forward static catalog specimens while keeping ripple, fixed/sticky placement, Extended FAB behavior, FAB menu, Toolbar integration, and release/state bookkeeping out of scope.
