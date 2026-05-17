# Axismundi v3.5.2 — Icon button #2 Phase 2 Plan

> **Status**: PLAN-ONLY v1.1. Awaiting review/approval before Phase 2 execution.
> **Date**: 2026-05-16
> **Source authority**: `docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md` + Phase 1 audit trio.
> **Reference template**: Button v3.5.1 Phase 2 plan + `lab-button.css` / `lab-button-pattern.html`.
> **Pre-entry decision**: Option (b) inherited from Button #1 — animated ripple is deferred to Ripple v2 / BACKLOG #25.

---

## §0 — Plan Scope And Gate

This is a plan-only artifact. It does not create Phase 2 deliverables.

Purpose:

```txt
1. Define exact Phase 2 deliverables.
2. Lock non-goals.
3. Set runtime audit migration decision for Phase 2.
4. Map validation and G1-G10 gate readiness.
5. Surface risks before execution.
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
| `lab-icon-button.css` | `products/reference-implementations/axismundi-lab/modules/icon-button/lab-icon-button.css` | Lab-internal demo scaffolding and documentation comments on top of baseline `.ax-icon-button`. |
| `lab-icon-button-pattern.html` | `products/reference-implementations/axismundi-lab/modules/icon-button/lab-icon-button-pattern.html` | Full variant/state/accessibility pattern page for Icon button. |

Not created:

```txt
lab-icon-button.js
```

Reason:

```txt
Option (b) defers animated ripple to Ripple v2. Icon button needs no JS
for Phase 2's static lab pattern.
```

### §1.2 — Runtime Audit Migration

Decision for Phase 2:

```txt
Do NOT move, copy, or stub ICON-BUTTON-RUNTIME-AUDIT.md during Phase 2.
```

Reason:

```txt
Phase 2's job is to add the lab CSS + pattern HTML implementation surface.
Moving historical docs is bookkeeping with cross-reference fallout. Keep it
for Phase 5 mechanical close or a separate v3.5.x documentation cleanup.
```

Phase 2 should only preserve the already-authored disposition in `ICON-BUTTON-SPEC-AUDIT.md §5`.

### §1.3 — Size Scope

Decision:

```txt
Default size only.
```

Phase 2 must not add XS/S/M/L/XL size variants. The lab pattern may mention the deferral, but it must not implement those sizes.

### §1.4 — Disabled Split

Decision:

```txt
Use Button's verified split:
  §5  Disabled — native disabled
  §5a Disabled — aria-disabled plugin-managed
```

### §1.5 — Stale SVG Public Snippet

Decision:

```txt
Do not edit style-guide.html.
Do not edit BACKLOG.md during Phase 2.
Keep BACKLOG #28 as candidate routing note only.
```

---

## §2 — `lab-icon-button.css` Scope

### §2.1 — Purpose

The CSS file is lab-internal scaffolding for the pattern page. The component styling remains baseline-owned by:

```txt
components.css §0   state-layer foundation
components.css §3   icon-button baseline
icons.css §1        Material Symbols base
icons.css §5        .ax-icon-button glyph integration
```

### §2.2 — Allowed Content

```txt
Allowed:
  - .lab-icon-button-demo wrapper layout
  - .lab-icon-button-section layout
  - .lab-icon-button-row / grid layout
  - specimen captions and code-snippet styling
  - module-private --lab-icon-button-* layout variables
  - comments declaring dependency profile and audit doc references
```

Scoped visualization helpers are allowed only if:

```txt
selector includes .lab-icon-button-demo ancestor
property is demonstrative only
no color token / size / border / radius override of baseline variant styling
```

Expected file size:

```txt
80-150 lines
```

### §2.3 — Forbidden Content

```txt
Forbidden:
  - unscoped .ax-icon-button overrides
  - changes to variant colors
  - changes to baseline width/height/min-target
  - changes to glyph font-size
  - new --md-sys-* tokens
  - ripple wiring or data-ax-ripple attributes
  - <md-ripple> references
  - JS dependencies
```

Verification grep:

```powershell
rg -n "^\\.ax-icon-button|^[^.].*\\.ax-icon-button" products\reference-implementations\axismundi-lab\modules\icon-button\lab-icon-button.css
```

Any match without `.lab-icon-button-demo` scope is suspect.

---

## §3 — `lab-icon-button-pattern.html` Scope

### §3.1 — Purpose

The pattern page demonstrates the baseline-supported Icon button contract in one lab-internal page:

```txt
variants
selected/unselected state
native disabled
aria-disabled plugin-managed
accessible name contract
has-state-layer opt-out
code snippets
cross-references
```

### §3.2 — Required Section Structure

```txt
§1  Page header
    - title
    - "Experimental lab preview" banner
    - links to style-guide.html#components-icon-button and audit docs

§2  Variants (4)
    - filled
    - tonal
    - outlined
    - standard
    All use <button type="button">, aria-label, has-state-layer,
    and Material Symbols glyph span.

§3  Selected / unselected toggle states (8)
    - 4 unselected aria-pressed="false"
    - 4 selected aria-pressed="true"
    Use native button semantics; no JS behavior.
    Required user-facing caption (EN):
      "Static catalog specimens — aria-pressed values are fixed; no JS
      toggle handler. Production usage wires actual toggle behavior at
      the integrator level (theme JS / block editor / plugin)."
    Required user-facing caption (KO):
      "정적 카탈로그 specimen — aria-pressed 값 고정, JS toggle 없음.
      실제 사용 시 통합 레벨(theme JS / block editor / plugin)에서 toggle
      handler 배선."

§4  Accessible name contract
    - 1 correct aria-label specimen
    - 1 correct aria-labelledby specimen
    - 1 documented anti-pattern snippet only for missing label
      (do not render an inaccessible live control)

§5  Disabled — native attribute (4)
    - filled / tonal / outlined / standard
    - native disabled

§5a Disabled — aria-disabled plugin-managed (1)
    - one standard or filled specimen
    - aria-disabled="true"
    - explicit bilingual caption: app/plugin must prevent activation

§6  has-state-layer opt-out demo (1 + 1)
    - one with has-state-layer
    - one without has-state-layer
    - explicit bilingual caption that missing overlay is intentional

§7  Code snippets
    - canonical variant
    - selected state
    - aria-label
    - aria-labelledby
    - native disabled
    - aria-disabled plugin-managed

§8  Cross-references
    - Phase 0 report
    - Phase 1 plan
    - SPEC / MEASUREMENT / WP-MAPPING
    - ICON-SYSTEM-AUDIT
    - ICON-BUTTON-RUNTIME-AUDIT
```

Expected size:

```txt
260-380 lines
```

### §3.3 — Canonical Markup

```html
<button class="ax-icon-button is-standard has-state-layer" type="button" aria-label="Search">
  <span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true" draggable="false">search</span>
</button>
```

Rules:

```txt
- every rendered control has an accessible name
- glyph span is aria-hidden
- no div/span role=button
- no action anchor unless demonstrating navigation, which Phase 2 does not need
- no ripple markup
```

---

## §4 — Dependency Header For CSS

`lab-icon-button.css` should start with a dependency header:

```css
/* ============================================================
 * lab-icon-button.css
 *
 * Lab-internal pattern page scaffolding on top of baseline:
 *   - components.css §3 Icon button — CURRENT baseline primitive
 *   - components.css §0 State-layer foundation — CURRENT
 *   - icons.css §1 + §5 Material Symbols integration — CURRENT
 *
 * Infrastructure dependencies:
 *   - icon-system/ — CURRENT unconditional
 *
 * Target / future dependencies (NOT wired at v3.5.2):
 *   - lab/modules/ripple/ — TARGET, deferred to Ripple v2
 *     (BACKLOG #25 + #27)
 *
 * Audit docs:
 *   - docs/ICON-BUTTON-SPEC-AUDIT.md
 *   - docs/ICON-BUTTON-MEASUREMENT-AUDIT.md
 *   - docs/ICON-BUTTON-WP-MAPPING.md
 *
 * Baseline/public files remain unchanged.
 * ============================================================ */
```

---

## §5 — G1-G10 Gate Readiness

| Gate | Phase 2 target | Verification |
|---|---|---|
| G1 validator | preserve PASS | `python tools\validators\validate_theme_pilot.py` |
| G2 baseline untouched | preserve | mtime/readback for components.css, icons.css, style-guide.html |
| G3 publish clean | no publish change expected | optional, not required for Phase 2 |
| G4 module artifacts | create two files | `lab-icon-button.css` + `lab-icon-button-pattern.html` |
| G5 changelog | later | Phase 5 |
| G6 visual QA | later | Phase 3 |
| G7 real controls | achieve | rendered controls are `<button>` |
| G8 native semantics | achieve | all buttons have explicit `type="button"` |
| G9 WCAG SC accuracy | already achieved | MEASUREMENT cites AA + AAA target size |
| G10 3-doc audit | already achieved | Phase 1 trio exists |

---

## §6 — Validation Plan

After approved Phase 2 execution:

```powershell
# 1. Create exactly two deliverable artifacts
Test-Path products\reference-implementations\axismundi-lab\modules\icon-button\lab-icon-button.css
Test-Path products\reference-implementations\axismundi-lab\modules\icon-button\lab-icon-button-pattern.html
Test-Path products\reference-implementations\axismundi-lab\modules\icon-button\lab-icon-button.js
# expected: True, True, False

# 2. Marker check
rg -n "\[NEXT SESSION:\]" products\reference-implementations\axismundi-lab\modules\icon-button
# expected: no hits

# 3. Native semantics
rg -n "<div role=\"button\"|<span class=\"ax-icon-button|<a class=\"ax-icon-button" products\reference-implementations\axismundi-lab\modules\icon-button\lab-icon-button-pattern.html
# expected: no hits

rg -n "<button(?![^>]*type=)" products\reference-implementations\axismundi-lab\modules\icon-button\lab-icon-button-pattern.html
# expected: no rendered button without type

# 4. Accessible names
rg -n "<button[^>]*class=\"[^\"]*ax-icon-button" products\reference-implementations\axismundi-lab\modules\icon-button\lab-icon-button-pattern.html
# then inspect: each rendered button has aria-label or aria-labelledby

# 5. No ripple wiring
rg -n "data-ax-ripple|md-ripple|lab-ripple|ripple\\.js" products\reference-implementations\axismundi-lab\modules\icon-button
# expected: no hits except explanatory text if any

# 6. Validator
python tools\validators\validate_theme_pilot.py
# expected: 1.000 / 1.000 / 1.000 / 1.000 PASS
```

Optional phase bookkeeping after successful execution:

```txt
Update ICON-BUTTON-SPEC-AUDIT.md §10 criterion #4 from "Phase 2 readiness"
to "Phase 2 artifact completeness" PASS.

Do not update CURRENT-STATE.md unless the user wants a phase-boundary
snapshot. Do not update NEXT-SESSION.md unless ending or handing off the
session.
```

---

## §7 — Explicit Non-Goals

Phase 2 does not:

```txt
- edit components.css
- edit icons.css
- edit style-guide.html
- edit theme.json
- edit BACKLOG.md
- edit CHANGELOG.md
- edit ROADMAP.md
- edit CURRENT-STATE.md unless explicitly requested as phase-boundary bookkeeping
- edit NEXT-SESSION.md
- move/copy/stub ICON-BUTTON-RUNTIME-AUDIT.md
- create lab-icon-button.js
- wire current ripple
- implement Ripple v2
- implement Lab Preview Routes
- correct stale SVG snippets in public style-guide
- register WordPress block styles
- create plugin/editor runtime code
- implement icon picker UI
- implement social/ActivityPub actions
- add XS/S/M/L/XL size variants
```

---

## §8 — Risks

### Risk A — Shadow Baseline

If `lab-icon-button.css` styles `.ax-icon-button` globally, it becomes a second baseline.

Mitigation:

```txt
Only demo scaffolding and scoped helpers under .lab-icon-button-demo.
No unscoped .ax-icon-button variant overrides.
```

### Risk B — Accessible Name Failure

Icon-only controls are easy to render without names.

Mitigation:

```txt
Every rendered specimen has aria-label or aria-labelledby.
The missing-label anti-pattern appears only inside escaped code snippets.
```

### Risk C — Runtime Audit Migration Creep

Moving `ICON-BUTTON-RUNTIME-AUDIT.md` during Phase 2 would change historical cross-references.

Mitigation:

```txt
Do not move/copy/stub during Phase 2.
Keep migration as a future bookkeeping item.
```

### Risk D — `aria-disabled` Misread As Native Disabled

`aria-disabled` does not block click activation.

Mitigation:

```txt
Separate §5 native disabled from §5a plugin-managed aria-disabled.
Add bilingual warning captions.
```

### Risk E — Ripple Drift

Icon button is a ripple target, so implementers may be tempted to wire current ripple.

Mitigation:

```txt
No lab-icon-button.js.
No data-ax-ripple.
No lab-ripple dependency.
Ripple stays deferred to Ripple v2.
```

---

## §9 — Approval Gate

Phase 2 execution is blocked until this plan is approved.

Approved execution means:

```txt
Create lab-icon-button.css.
Create lab-icon-button-pattern.html.
Do not create JS.
Do not move runtime audit.
Run validation.
Report changed files and risks.
```

---

## §10 — One-Line Summary

```txt
Icon button Phase 2 should add exactly two lab-internal artifacts
(lab-icon-button.css + lab-icon-button-pattern.html), demonstrate four
variants, selected/unselected states, accessible-name contracts, native
disabled plus aria-disabled plugin-managed split, and has-state-layer
opt-out, while preserving baseline/public files, leaving runtime audit
migration for later, and keeping ripple deferred to Ripple v2.
```
