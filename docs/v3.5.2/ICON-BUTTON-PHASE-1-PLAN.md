# Axismundi v3.5.2 — Icon button #2 Phase 1 Plan

> **Status**: PLAN-ONLY v1.1. Awaiting review/approval before Phase 1 execution.
> **Date**: 2026-05-16
> **Preceded by**: `docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md`
> **Scope**: Plan the Phase 1 audit-doc authoring pass for Icon button #2.
> **Non-scope**: No audit docs are created by this plan; no runtime audit is moved; no baseline or public surface files are edited.

---

## §0 — Goal

Phase 1 will convert the Phase 0 findings into the standard Component Full-Spec audit body for Icon button #2.

The planned deliverables are:

```txt
products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-WP-MAPPING.md
```

Phase 1 is documentation authoring only. It does not create `lab-icon-button.css`, `lab-icon-button-pattern.html`, or `lab-icon-button.js`.

---

## §1 — Phase 1 Lock Decisions

This plan locks five items before execution so the Phase 1 writer does not inherit ontology ambiguity.

### §1.1 — Risk 5 Disposition: Runtime Audit Ownership

Decision:

```txt
Use option (a) from Phase 0:
  Icon button owns its component audits.
```

Execution model:

```txt
Do NOT physically move the historical runtime audit during Phase 1.

Instead:
  1. Create icon-button/docs/ for the new Component Full-Spec audit trio.
  2. Treat ICON-BUTTON-RUNTIME-AUDIT.md as a canonical historical reference.
  3. Add an explicit "Migration disposition" section to ICON-BUTTON-SPEC-AUDIT.md.
  4. Schedule the actual move/copy/stub decision for Phase 2 or a small follow-up,
     after the new icon-button/docs/ directory exists and review confirms wording.
```

Why not move during Phase 1?

```txt
Phase 1 is audit authoring. Moving historical docs is a file-ownership
operation with cross-reference fallout. Keep the plan sharp:
  - Phase 1 creates the new owner surface.
  - A later approved execution can migrate/stub the old runtime audit.
```

Preferred future migration shape:

```txt
Move/copy:
  modules/icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md
    -> modules/icon-button/docs/ICON-BUTTON-RUNTIME-AUDIT.md

Leave behind:
  either a short stub in icon-system/docs/
  or update icon-system docs to cross-reference the new owner path.
```

### §1.2 — Risk 6 Disposition: Size Variant Scope

Decision:

```txt
Default size only for v3.5.2 Icon button #2.
XS / S / M / L / XL expansion is deferred unless Phase 1 finds an
already-shipped baseline size system.
```

Current baseline inventory suggests default size only:

```txt
components.css §3:
  width/height: --comp-button-height (40px)
  min-width/min-height: --comp-touch-target (48px)
```

Phase 1 must document M3 size expectations honestly, but should follow the Button #1 pattern:

```txt
Baseline-present size:
  PASS / in scope

Absent M3 sizes:
  recorded as explicit deferral, not silently implemented
```

### §1.3 — Risk 7 Disposition: Disabled Split

Decision:

```txt
Reuse Button v3.5.1's verified disabled split:

§5  Disabled — native disabled
§5a Disabled — aria-disabled plugin-managed
```

Reason:

```txt
Native disabled blocks activation by platform behavior.
aria-disabled only communicates state and styling; the app/plugin must
block activation in its event handling.
```

Phase 1 must require this split in the future `lab-icon-button-pattern.html` plan.

### §1.4 — Risk 4 Disposition: Stale SVG Snippet

Decision:

```txt
Route stale Icon button SVG snippet/helper text to a new BACKLOG candidate:
  BACKLOG #28 — Icon button public specimen SVG wording cleanup
```

Phase 1 does not edit `style-guide.html`.

Phase 1 should record:

```txt
Problem:
  Actual Icon button specimens use Material Symbols spans.
  The public snippet/helper still uses SVG-era examples.

Disposition:
  Non-blocking documentation/public specimen mismatch.
  Candidate BACKLOG #28, separate public-surface cleanup.
```

This plan does not edit `BACKLOG.md`; it only defines the intended routing. A later approved bookkeeping step may add #28.

### §1.5 — `icon-button/docs/` Creation Boundary

Decision:

```txt
Plan-only stage:
  Do not create icon-button/docs/.

Phase 1 execution after approval:
  Create icon-button/docs/ and the three audit docs.
```

No `lab-icon-button.css` or pattern HTML is created until Phase 2.

---

## §2 — Phase 1 Deliverables

### §2.1 — `ICON-BUTTON-SPEC-AUDIT.md`

Required sections:

```txt
§0 Status / scope
§1 Authoritative inputs
§2 Baseline inventory
§3 Variant and state coverage
§4 Icon-system dependency contract
§5 Runtime audit migration disposition
§6 Ripple TARGET deferral
§7 Exceptions and deferred items
§8 M3 spec coverage and icon-button semantics
§9 Phase 2 readiness checklist
§10 SPEC verdict criteria
§10a Visible control principle and native semantics
§11 G1-G10 gate applicability
§12 References
§13 What this audit does NOT do
```

Must explicitly state:

```txt
components.css §0 state-layer foundation   CURRENT
components.css §3 icon-button baseline     CURRENT
icon-system/                               CURRENT unconditional
ripple/                                    TARGET, deferred to Ripple v2
```

Must preserve the ontology boundary:

```txt
icon-button/ owns the component.
icon-system/ owns glyph infrastructure.
```

Must include explicit Principle 1 / Principle 2 coverage:

```txt
Principle 1 — visible control:
  An icon button is visible by glyph, but its programmatic name is not
  visible text. Missing aria-label/equivalent name is a failure, not a
  minor documentation issue.

Principle 2 — native semantics:
  Action icon buttons use <button type="button">.
  Links are allowed only when the control performs navigation.
```

### §2.2 — `ICON-BUTTON-MEASUREMENT-AUDIT.md`

Required measurement coverage:

```txt
Container:
  - 40px nominal container from --comp-button-height
  - 48px minimum touch target from --comp-touch-target
  - full radius

Icon:
  - Material Symbols glyph size source
  - direct span vs .ax-icon selector coverage
  - pointer-events/user-select/drag hardening

Variants:
  - filled
  - tonal
  - outlined
  - standard

States:
  - default
  - selected / aria-pressed
  - disabled native
  - aria-disabled plugin-managed
  - hover/focus/pressed static state layer

Deferrals:
  - XS / S / M / L / XL expansion beyond current baseline
  - animated ripple
```

The measurement doc must inspect whether `icons.css` already covers direct `.material-symbols-rounded` sizing and hardening.

Required WCAG SC accuracy finding:

```txt
SC 2.5.8 Target Size (Minimum) AA:
  24px minimum target size
  met: Icon button touch target is 48px via --comp-touch-target

SC 2.5.5 Target Size (Enhanced) AAA:
  44px enhanced target size
  met: Icon button touch target is 48px via --comp-touch-target

Positive finding:
  Unlike Button #1's 40px nominal button height, Icon button's 48px
  touch-target expansion satisfies both AA and AAA target-size criteria.
  Cross-reference Button MEASUREMENT §4.5 for contrast.
```

### §2.3 — `ICON-BUTTON-WP-MAPPING.md`

Required mapping coverage:

```txt
Core/block editor surfaces:
  - toolbar icon buttons
  - inserter/action icon buttons
  - block controls
  - inspector/action icon buttons

Theme/front-end surfaces:
  - app bar actions
  - navigation actions
  - social/action rows
  - search/action affordances

Required contracts:
  - native <button type="button"> for actions
  - <a> only for navigation, not action buttons
  - accessible name via aria-label or equivalent
  - glyph span aria-hidden
  - icon-system/ glyph policy
  - plugin-managed aria-disabled caveat
```

Must separate:

```txt
WordPress core button block:
  not the primary mapping; icon-only controls are usually toolbar/action UI.

WordPress editor chrome:
  primary mapping surface for Icon button.
```

Required structural sections:

```txt
Core/block context inventory table:
  - core/buttons icon-only variant pressure
  - core/navigation action/icon affordances
  - core/social-links action/link boundary
  - core/post-comments-form reply/share icon affordances
  - core/search submit icon affordance
  - editor toolbar/block controls as primary icon-button surface

Accessible name contract:
  - icon-only controls require aria-label, aria-labelledby, or equivalent
  - glyph span remains aria-hidden
  - visible tooltip does not replace the accessible name unless correctly wired
  - missing accessible name is a Principle 1 failure

Anti-pattern inventory:
  - icon button without aria-label/equivalent accessible name
  - using core/button as an icon-only control where a dedicated icon-button
    mapping is required
  - hardcoded SVG that bypasses icon-system policy
  - decorative emoji used as the only glyph for a control
  - <a class="ax-icon-button"> for a non-navigation action
  - inert-looking controls without native disabled or managed aria-disabled
  - aria-disabled without event-handling guard
```

---

## §3 — Inputs To Read During Phase 1 Execution

Execution should read these files before authoring:

```txt
Phase 0:
  docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md

Framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/33-COMPONENT-INVENTORY.md

Baseline/public:
  products/reference-implementations/axismundi-lab/stylesheets/components.css
  products/reference-implementations/axismundi-lab/stylesheets/icons.css
  products/reference-implementations/axismundi-lab/style-guide.html

Icon-system:
  products/reference-implementations/axismundi-lab/modules/icon-system/docs/ICON-SYSTEM-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/icon-system/docs/ICON-FONT-POLICY.md
  products/reference-implementations/axismundi-lab/modules/icon-system/docs/INLINE-SVG-INVENTORY.md

Precedents:
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-MEASUREMENT-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-WP-MAPPING.md
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-MEASUREMENT-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-WP-MAPPING.md
```

---

## §4 — Execution Steps After Approval

After this plan is approved:

```txt
1. Create:
   products/reference-implementations/axismundi-lab/modules/icon-button/docs/

2. Draft:
   ICON-BUTTON-SPEC-AUDIT.md
   ICON-BUTTON-MEASUREMENT-AUDIT.md
   ICON-BUTTON-WP-MAPPING.md

3. Cross-reference each doc to:
   - the other two new audit docs
   - ICON-BUTTON-PHASE-0-REPORT.md
   - ICON-BUTTON-RUNTIME-AUDIT.md
   - ICON-SYSTEM-AUDIT.md
   - Button v3.5.1 and Chip v3.4.9 precedents

4. Record the runtime audit migration disposition.

5. Record BACKLOG #28 as a candidate, but do not edit BACKLOG.md unless
   explicitly authorized.

6. Run validator:
   python tools\validators\validate_theme_pilot.py
```

Phase 1 execution should not update `NEXT-SESSION.md`. `CURRENT-STATE.md` does not need a change until Phase 1 closes or a true phase boundary snapshot is requested.

---

## §5 — Test Plan

Plan validation:

```txt
- ICON-BUTTON-PHASE-1-PLAN.md exists under docs/v3.5.2/
- No icon-button/docs/ directory created during plan-only stage
- No baseline/public files changed
- No runtime audit moved
- Validator remains 1.000 PASS
```

Phase 1 execution validation after approval:

```txt
- 3 audit docs created
- [NEXT SESSION:] markers: 0
- Dependency profile appears in SPEC
- icon-system/ marked CURRENT unconditional, not conditional
- ripple/ marked TARGET, deferred to Ripple v2
- Runtime audit migration disposition appears in SPEC
- Disabled native/aria-disabled split appears in SPEC and WP-MAPPING
- Size-scope deferral appears in SPEC and MEASUREMENT
- MEASUREMENT cites SC 2.5.8 AA and SC 2.5.5 AAA as met by the 48px touch target
- WP-MAPPING includes core/block context inventory, accessible-name contract,
  and anti-pattern inventory
- SPEC includes Principle 1/2 coverage and a closing "What this audit does NOT do" section
- SPEC §8 is M3-focused and does not duplicate WP-MAPPING ownership
- BACKLOG #28 candidate appears as routing note, if not yet added to BACKLOG.md
- Validator remains 1.000 PASS
```

---

## §6 — Explicit Non-Goals

This plan does not authorize:

```txt
- editing components.css
- editing icons.css
- editing style-guide.html
- editing theme.json
- editing BACKLOG.md
- editing CHANGELOG.md
- editing ROADMAP.md
- editing CURRENT-STATE.md
- editing NEXT-SESSION.md
- moving ICON-BUTTON-RUNTIME-AUDIT.md
- creating a stub in icon-system/docs/
- creating lab-icon-button.css
- creating lab-icon-button-pattern.html
- creating lab-icon-button.js
- wiring current ripple
- implementing Ripple v2
- implementing Lab Preview Routes
- correcting stale SVG snippets in the public style guide
```

---

## §7 — Risks

### Risk A — Runtime Audit Migration Creep

Moving the historical runtime audit during Phase 1 could create cross-reference churn.

Mitigation:

```txt
Phase 1 records the migration disposition only.
Actual move/copy/stub work requires a separate explicit approval.
```

### Risk B — Icon-System Absorbs Component Ownership

Because the old audit lives under `icon-system/`, Phase 1 could accidentally classify Icon button as infrastructure-owned.

Mitigation:

```txt
Every audit doc states:
  icon-button/ owns the component.
  icon-system/ owns glyph infrastructure.
```

### Risk C — Selector Gap Misclassified

Direct Material Symbols spans may already be covered by `icons.css`, while `components.css §3` only names `> svg` and `> .ax-icon`.

Mitigation:

```txt
MEASUREMENT audit inspects icons.css before recommending any baseline amendment.
No baseline change in Phase 1.
```

### Risk D — Stale Public Snippet Becomes Scope Creep

The SVG snippet/helper mismatch is real, but correcting `style-guide.html` is public-surface work.

Mitigation:

```txt
Route to candidate BACKLOG #28.
Do not edit style-guide.html in Phase 1.
```

### Risk E — Size Variant Scope Drift

M3 may define additional icon button sizes, but current baseline appears default-size only.

Mitigation:

```txt
Document default-size-only scope.
Defer absent size variants honestly.
```

---

## §8 — Approval Gate

Phase 1 execution is blocked until this plan is approved.

Approved execution means:

```txt
Create icon-button/docs/.
Create the three audit docs.
Do not move runtime audit.
Do not edit baseline/public files.
Run validator.
Report changed files and unresolved risks.
```

---

## §9 — One-Line Summary

```txt
v3.5.2 Phase 1 should author the Icon button three-doc audit set under
icon-button/docs/, lock icon-system/ as CURRENT unconditional, keep
ripple/ deferred, record runtime-audit migration as a disposition rather
than a move, and route stale SVG public wording to candidate BACKLOG #28.
```
