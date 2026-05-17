# Axismundi v3.5.3 — Card #9 Phase 1 Plan

> **Status**: PLAN-ONLY v1.0. Awaiting review/approval before Phase 1 execution.
> **Date**: 2026-05-16
> **Preceded by**: `docs/v3.5.3/CARD-PHASE-0-REPORT.md`
> **Scope**: Plan the Phase 1 audit-doc authoring pass for Card #9.
> **Non-scope**: No audit docs are created by this plan; no Card module files, baseline files, state files, or handoff files are edited.

---

## §0 — Goal

Phase 1 will convert the Phase 0 Card findings into the standard Component Full-Spec audit body for Card #9.

The planned deliverables after approval are:

```txt
products/reference-implementations/axismundi-lab/modules/card/docs/CARD-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/card/docs/CARD-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/card/docs/CARD-WP-MAPPING.md
```

Phase 1 is documentation authoring only. It does not create `lab-card.css`, `lab-card-pattern.html`, or `lab-card.js`.

---

## §1 — Phase 1 Lock Decisions

This plan locks the Card-specific decisions that should not be rediscovered during audit authoring.

### §1.1 — Runtime Audit Migration

Decision:

```txt
N/A.
```

Card has no prior runtime audit equivalent to Icon button's historical `ICON-BUTTON-RUNTIME-AUDIT.md`. Phase 1 creates a new Card audit surface from the standard three-doc pattern only.

Execution implication:

```txt
Create modules/card/docs/ only after this plan is approved.
Do not move, copy, stub, or migrate any existing runtime audit.
```

### §1.2 — Size / Flow Scope

Decision:

```txt
Card v3.5.3 uses the baseline responsive width-flow model.
No fixed XS/S/M/L/XL card size system is introduced.
```

Phase 1 should measure and document:

```txt
--comp-card-padding
--comp-card-radius
elevation level0 / level1 / level2 hover
outlined border width/color
media slot radius/margins
title/subtitle typography
actions slot layout
disabled opacity 0.38
```

Do not invent:

```txt
fixed card widths
grid systems
aspect-ratio presets beyond media slot documentation
new size variants
```

### §1.3 — Disabled Split

Decision:

```txt
Card disabled is Pattern B by default:
  whole-container disabled opacity 0.38.
```

Phase 1 must split disabled specimens and documentation into three cases:

```txt
§5  Non-interactive locked card
    <article class="card ..." aria-disabled="true">
    Pattern B opacity. No activation exists.

§5a Native disabled action card
    <button class="card card--interactive" type="button" disabled>
    Pattern B opacity plus platform activation blocking.

§5b aria-disabled plugin-managed interactive card
    <button class="card card--interactive" type="button" aria-disabled="true">
    Pattern B opacity only. Integrator/plugin must block activation.
```

Pattern distinction:

```txt
Pattern B:
  Card disabled, whole-container opacity.

Pattern A:
  State-layer / control disabled treatment used by Button and Icon button.

Card Phase 1 must not collapse these two patterns.
```

### §1.4 — Native Semantics Decision Tree

Decision:

```txt
Static content Card:
  <article>, <section>, or <div> depending content context.
  No click handler.
  No tabindex.
  No role="button".

Action Card:
  <button class="card card--interactive" type="button">

Navigation Card:
  <a class="card card--interactive" href="...">

Forbidden:
  <article class="card card--interactive" role="button" tabindex="0">
```

This is Card's highest-risk Phase 1 rule. The SPEC and WP-MAPPING docs must make it first-class.

### §1.5 — WordPress Block Bridge

Decision:

```txt
blocks.css core/group card styles are CURRENT partial bridge.
They are audited as current behavior, not proposed future work.
```

Current bridge:

```txt
.wp-block-group.is-style-card-filled
.wp-block-group.is-style-card-elevated
.wp-block-group.is-style-card-outlined
```

Framework citation:

```txt
PUBLIC-SURFACE-CHARTER §3.4 and PROMOTION-CRITERIA §4 classify
WordPress block style variations / register_block_style() style mapping
as theme-can territory.
```

Phase 1 must also record the comment drift:

```txt
blocks.css comment says `.ax-card` and components.css §3.
Actual baseline is `.card` in components.css §5.
```

Disposition:

```txt
Audit as current partial bridge.
Do not edit blocks.css in Phase 1.
Do not add a BACKLOG entry unless explicitly requested after review.
```

### §1.6 — Composition Slot Scope

Decision:

```txt
Phase 1 should require Phase 2 to cover the baseline Card slots:
  .card__media
  .card__title
  .card__subtitle
  card body content
  .card__actions
```

Conservative Phase 2 specimen scope:

```txt
filled / elevated / outlined variants
media slot
title + subtitle
body content
actions slot
interactive action card
interactive navigation card
disabled split
core/group bridge reference specimen or documentation snippet
```

Not in v3.5.3:

```txt
complex media galleries
rich nested card layouts
drag/drop cards
expandable cards
card grids
runtime action wiring
```

### §1.7 — Icon-System Composition

Decision:

```txt
Card direct icon-system dependency = NONE.
Card composition dependency = CURRENT conditional via .card__actions.
```

Meaning:

```txt
Card owns the container and actions slot.
Button / Icon button own the nested controls.
icon-system/ owns glyph infrastructure for nested Icon button or icons.
```

Phase 1 must not classify Card itself as an icon-system consumer.

### §1.8 — Ripple Consumer-State

Decision:

```txt
Base Card:
  ripple/ = NONE

Interactive/action Card:
  ripple/ = CANDIDATE
  Deferred to Ripple v2 / future consumer-state amendment.
```

Required cross-references:

```txt
BACKLOG #25  Ripple v2 contract
BACKLOG #26  Matrix row #36 allowlist correction
```

Do not wire current `lab/modules/ripple/` during Phase 1 or Phase 2.

---

## §2 — Phase 1 Deliverables

### §2.1 — `CARD-SPEC-AUDIT.md`

Required sections:

```txt
§0  Status / scope
§1  Authoritative inputs
§2  Baseline Card inventory
§3  Public specimen inventory
§4  Variant and slot coverage
§5  Native semantics decision tree
§6  Disabled Pattern B split
§7  Dependency profile
§8  WordPress bridge summary
§9  Exceptions and deferred items
§10 Phase 2 readiness checklist
§11 SPEC verdict criteria
§12 G1-G10 gate applicability
§13 References
§14 What this audit does NOT do
```

Must explicitly state:

```txt
components.css §5 Card baseline            CURRENT
components.css §0 state-layer foundation   CURRENT for interactive card
blocks.css core/group card bridge          CURRENT partial
ripple/                                    NONE for base Card
                                           CANDIDATE for action/interactive Card
icon-system/                               CURRENT conditional via composition
```

Must include Principle 1 / Principle 2 coverage:

```txt
Principle 1 — visible control:
  Static cards are content containers, not controls.
  Interactive action cards must be real visible controls.
  Fake clickable articles violate the visible-control principle.

Principle 2 — native semantics:
  Action card = <button type="button">.
  Navigation card = <a href="...">.
  Static content card = article/section/div with no fake interactivity.
```

### §2.2 — `CARD-MEASUREMENT-AUDIT.md`

Required measurement coverage:

```txt
Container:
  - padding
  - radius
  - fill/elevation/outline variants
  - hover elevation for interactive elevated card
  - focus-visible outline for interactive cards

Slots:
  - media margin / border radius / overflow behavior
  - title typography
  - subtitle typography
  - body text inheritance
  - actions slot spacing/alignment

Disabled:
  - Pattern B whole-container opacity 0.38
  - native disabled button-card
  - aria-disabled non-interactive card
  - aria-disabled plugin-managed interactive card caveat

WordPress bridge:
  - core/group style variant parity with baseline card variants
```

WCAG SC accuracy must be nuanced:

```txt
Base Card:
  Not a control. SC 2.5.8 / 2.5.5 target-size checks are not directly
  applicable to static content cards.

Interactive action/navigation Card:
  The whole card is the target. Target-size minimum is trivially met
  for normal card dimensions, but Principle 1/2 native semantics are
  the primary accessibility gate.

Nested actions:
  Button and Icon button target-size details belong to their own audits.
```

### §2.3 — `CARD-WP-MAPPING.md`

Required mapping coverage:

```txt
Core/block context inventory table:
  - core/group as primary card bridge
  - core/columns as layout wrapper around cards
  - core/row as horizontal layout wrapper
  - core/media-text as related but not the Card primitive
  - core/query loop / post template card-like outputs
```

Current bridge inventory:

```txt
blocks.css:
  .wp-block-group.is-style-card-filled
  .wp-block-group.is-style-card-elevated
  .wp-block-group.is-style-card-outlined
```

Required contracts:

```txt
Theme-can:
  register_block_style() / block style variations for core/group.

Plugin-should:
  custom runtime card blocks, dynamic cards, editor UI, data-bound cards,
  drag/drop, accordions, expandable cards, and non-native interaction wiring.
```

Required anti-pattern inventory:

```txt
- clickable <article> with role="button" tabindex="0"
- clickable <div class="card">
- nested interactive controls inside a button-card
- action card implemented as <a> when it does not navigate
- navigation card implemented as <button> when it navigates
- disabled-looking interactive card without native disabled or managed guard
- treating aria-disabled as activation blocking
- bypassing Button/Icon button components inside .card__actions
- treating blocks.css card bridge as baseline component authority
```

Must include a Card semantics decision tree:

```txt
Is the card just content?
  article/section/div.

Does the whole card trigger an action?
  button type="button".

Does the whole card navigate?
  anchor with href.

Does only a nested action activate?
  static card + .card__actions containing Button/Icon button.
```

---

## §3 — Inputs To Read During Phase 1 Execution

Execution should read:

```txt
Phase 0:
  docs/v3.5.3/CARD-PHASE-0-REPORT.md

Framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/33-COMPONENT-INVENTORY.md
  docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md

Baseline/public:
  products/reference-implementations/axismundi-lab/stylesheets/components.css
  products/reference-implementations/axismundi-lab/stylesheets/blocks.css
  products/reference-implementations/axismundi-lab/style-guide.html

Precedents:
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-MEASUREMENT-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-WP-MAPPING.md
  products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-MEASUREMENT-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-WP-MAPPING.md
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-MEASUREMENT-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-WP-MAPPING.md
```

---

## §4 — Execution Steps After Approval

After this plan is approved:

```txt
1. Create:
   products/reference-implementations/axismundi-lab/modules/card/docs/

2. Draft:
   CARD-SPEC-AUDIT.md
   CARD-MEASUREMENT-AUDIT.md
   CARD-WP-MAPPING.md

3. Cross-reference each doc to:
   - the other two new audit docs
   - CARD-PHASE-0-REPORT.md
   - Button v3.5.1 precedent
   - Icon button v3.5.2 precedent
   - Chip v3.4.9 precedent
   - v3.5.0 public surface framework docs

4. Record blocks.css bridge as CURRENT partial.

5. Record Card action ripple as CANDIDATE, not TARGET.

6. Record icon-system as composition-only conditional.

7. Run validator:
   python tools\validators\validate_theme_pilot.py
```

Phase 1 execution should not update `NEXT-SESSION.md`. `CURRENT-STATE.md` does not need a change for plan execution; use phase-boundary updates only.

---

## §5 — Test Plan

Plan validation:

```txt
- CARD-PHASE-1-PLAN.md exists under docs/v3.5.3/
- No modules/card/ directory created during plan-only stage
- No baseline/public files changed
- No state/handoff files changed
- Validator remains 1.000 PASS
```

Phase 1 execution validation after approval:

```txt
- 3 audit docs created
- [NEXT SESSION:] markers: 0
- Dependency profile appears in SPEC
- blocks.css core/group bridge marked CURRENT partial
- base Card ripple marked NONE
- interactive/action Card ripple marked CANDIDATE
- icon-system marked CURRENT conditional via composition
- Native semantics decision tree appears in SPEC and WP-MAPPING
- Disabled Pattern B split appears in SPEC, MEASUREMENT, and WP-MAPPING
- WP-MAPPING includes core/block context inventory and anti-pattern inventory
- MEASUREMENT includes Card-specific WCAG applicability nuance
- SPEC includes a closing "What this audit does NOT do" section
- Validator remains 1.000 PASS
```

---

## §6 — Explicit Non-Goals

This plan does not authorize:

```txt
- editing components.css
- editing blocks.css
- editing style-guide.html
- editing tokens.css
- editing theme.json
- editing BACKLOG.md
- editing CHANGELOG.md
- editing ROADMAP.md
- editing CURRENT-STATE.md
- editing NEXT-SESSION.md
- creating modules/card/docs/
- creating CARD-SPEC-AUDIT.md
- creating CARD-MEASUREMENT-AUDIT.md
- creating CARD-WP-MAPPING.md
- creating lab-card.css
- creating lab-card-pattern.html
- creating lab-card.js
- wiring current ripple
- implementing Ripple v2
- implementing Lab Preview Routes
- renaming .card to .ax-card
- correcting blocks.css comment drift
- registering WordPress block styles
```

---

## §7 — Risks

### Risk A — Fake Interactive Card Creep

Clickable cards are commonly implemented as clickable articles or divs.

Mitigation:

```txt
SPEC and WP-MAPPING must include the native semantics decision tree and
explicitly reject <article role="button" tabindex="0">.
```

### Risk B — Blocks.css Bridge Misclassified As Future Work

Card already has a current partial `core/group` bridge.

Mitigation:

```txt
WP-MAPPING treats .wp-block-group.is-style-card-* as CURRENT partial and
uses CHARTER §3.4 / PROMOTION §4 as authority.
```

### Risk C — Disabled Pattern A/B Collapse

Button/Icon button disabled state uses control-level Pattern A. Card disabled uses Pattern B whole-container opacity.

Mitigation:

```txt
MEASUREMENT and SPEC split Pattern B Card disabled from state-layer Pattern A.
```

### Risk D — Composition Dependency Overstated

Card action slots may contain Icon button, but Card does not own glyph infrastructure.

Mitigation:

```txt
Dependency profile says icon-system/ = CURRENT conditional via composition,
not direct Card dependency.
```

### Risk E — Ripple Consumer-State Over-Promotion

The v3.5.0 matrix mentions Card action surfaces, but current lab ripple allowlist does not include Card.

Mitigation:

```txt
Base Card = NONE.
Interactive/action Card = CANDIDATE.
Do not promote to TARGET until Ripple v2 or a separate design decision.
```

### Risk F — Slot Scope Creep

Cards can become arbitrarily rich containers.

Mitigation:

```txt
Phase 1 locks v3.5.3 Phase 2 to baseline slots and representative specimens,
not every possible card composition.
```

---

## §8 — Approval Gate

Phase 1 execution is blocked until this plan is approved.

Approved execution means:

```txt
Create modules/card/docs/.
Create the three audit docs.
Do not edit baseline/public/state/handoff files.
Run validator.
Report changed files, validation, and unresolved risks.
```

If review finds gaps:

```txt
Revise this plan to v1.1.
Re-run validator.
Wait for approval again.
```

---

## §9 — One-Line Summary

```txt
v3.5.3 Phase 1 should author the Card three-doc audit set under
modules/card/docs/, lock Card's article/button/anchor semantics decision
tree, audit the existing core/group bridge as CURRENT partial, keep base
Card ripple NONE and action Card ripple CANDIDATE, record icon-system only
as conditional composition through card actions, and split Card disabled
Pattern B from Button/Icon button Pattern A.
```
