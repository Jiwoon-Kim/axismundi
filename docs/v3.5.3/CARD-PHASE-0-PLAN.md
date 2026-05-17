# Axismundi v3.5.3 — Card #9 Phase 0 Plan

> **Status**: PLAN-ONLY v1.0. Awaiting review/approval before Phase 0 report execution.
> **Date**: 2026-05-16
> **Component**: Card #9
> **Category**: Component Full-Spec
> **Routing**: Codex plan-first → review → Phase 0 report execution.

---

## §0 — Goal

Prepare the Phase 0 inventory and ontology framing pass for **Card #9**, the third Wave 1 Component Full-Spec module after Button #1 and Icon button #2.

Phase 0 execution will produce:

```txt
docs/v3.5.3/CARD-PHASE-0-REPORT.md
```

This plan does **not** create `lab/modules/card/`, audit docs, lab CSS, pattern HTML, or any baseline/public edits.

---

## §1 — Why Card Next

Card is the next mainline candidate because it is:

```txt
TOC row:        #9
TOC group:      Containers
Category:       Component Full-Spec
Current status: TODO
Wave:           1
WP mapping:     high, via core/group style variants
Dependencies:   ripple/ only for action/interactive surfaces
```

Compared with Button and Icon button:

```txt
Button #1:
  action component, ripple TARGET, icon-system conditional

Icon button #2:
  action component, ripple TARGET, icon-system unconditional

Card #9:
  container component, ripple CANDIDATE/TARGET only for interactive or
  action-surface variants; base visual card does not require ripple
```

This makes Card a good next framework test: it is a high-frequency component with weaker infrastructure coupling.

---

## §2 — Inputs To Read During Phase 0 Execution

### §2.1 — Framework

```txt
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/33-COMPONENT-INVENTORY.md
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
```

### §2.2 — Baseline / Public Specimens

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css §5 Card
products/reference-implementations/axismundi-lab/style-guide.html #components-card
products/reference-implementations/axismundi-lab/stylesheets/blocks.css core/group card styles
```

### §2.3 — Precedents

```txt
Button v3.5.1:
  docs/v3.5.1/BUTTON-PHASE-0-REPORT.md
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-MEASUREMENT-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-WP-MAPPING.md

Icon button v3.5.2:
  docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md
  products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-MEASUREMENT-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-WP-MAPPING.md

Chip v3.4.9:
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-MEASUREMENT-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-WP-MAPPING.md
```

---

## §3 — Phase 0 Questions To Answer

### §3.1 — Component Classification

Confirm:

```txt
Card #9 = Component Full-Spec
Status before v3.5.3 = TODO
Target module = card/
```

### §3.2 — Baseline Inventory

Inventory `components.css §5`:

```txt
.card
.card--filled
.card--elevated
.card--outlined
.card--interactive
.card--interactive.card--elevated:hover
.card--interactive:focus-visible
.card--outlined.card--interactive:focus-visible
.card[aria-disabled="true"]
.card:disabled
.card__media
.card__title
.card__subtitle
.card__actions
```

Inventory `style-guide.html #components-card`:

```txt
Variants:
  - filled article
  - elevated article
  - outlined article

Interactive:
  - elevated interactive native button with has-state-layer

Disabled:
  - elevated article with aria-disabled="true"
```

### §3.3 — WordPress Block Bridge

Phase 0 must inspect `blocks.css` because Card already has a partial WP bridge:

```txt
.wp-block-group.is-style-card-filled
.wp-block-group.is-style-card-elevated
.wp-block-group.is-style-card-outlined
```

Key question:

```txt
Are these block styles already an intentional public bridge,
or merely early baseline/pilot scaffolding that Phase 1 should audit?
```

This is Card-specific; Button and Icon button did not start with this much block-level mapping already in CSS.

### §3.4 — Ripple Consumer-State

Card's dependency on ripple must be narrower than Button/Icon button:

```txt
Base visual card:
  ripple/ = NONE

Interactive card or card action surface:
  ripple/ = CANDIDATE or TARGET, deferred to Ripple v2
```

Phase 0 must decide the exact label:

```txt
Option A:
  Card base = NONE; Card action/interactive = CANDIDATE
  Reason: v3.5.1 found Card action is not in current ripple allowlist.

Option B:
  Card base = NONE; Card action/interactive = TARGET
  Reason: matrix row #36 says Card action is a designed ripple consumer,
  even though current allowlist lacks it.
```

Recommendation for Phase 0 report:

```txt
Use Option A unless evidence shows a current explicit card-action
ripple design contract. This aligns with BACKLOG #26: Card action is
outside the current 7-selector allowlist and should not be promoted from
CANDIDATE to TARGET casually.
```

### §3.5 — Native Semantics

Phase 0 must distinguish:

```txt
Non-interactive cards:
  <article class="card ...">

Interactive cards:
  <button class="card card--interactive ..."> for actions
  <a class="card card--interactive ..."> only for real navigation

Anti-pattern:
  <article class="card card--interactive" tabindex="0" role="button">
```

This will become a central Phase 1 SPEC/WP-MAPPING requirement.

---

## §4 — Expected Phase 0 Risks

Phase 0 should explicitly evaluate these risks.

### Risk 1 — Card Ripple Consumer-State Ambiguity

Card is listed in v3.5.0 ripple consumers, but v3.5.1 later clarified current allowlist state:

```txt
Card action = not in current lab-ripple allowlist
```

Likely disposition:

```txt
Base Card = NONE
Interactive/action Card = CANDIDATE or TARGET, deferred to Ripple v2
```

### Risk 2 — Blocks.css Already Has Card Bridge

`blocks.css` already maps `core/group` style variants to card-like visuals.

Risk:

```txt
Phase 1 WP-MAPPING may falsely treat the WordPress bridge as future work,
when a partial implementation already exists.
```

Disposition:

```txt
Phase 0 must inventory this as CURRENT partial bridge / audit target.
```

### Risk 3 — Interactive Card Semantics

Interactive cards are easy to implement as clickable articles/divs.

Disposition:

```txt
Phase 1 must enforce Button/Icon button Principle 1/2:
visible interactive control must map to native action/link semantics.
```

### Risk 4 — Disabled Pattern B

Baseline uses Pattern B whole-container `opacity: 0.38` and supports `aria-disabled`.

Disposition:

```txt
Phase 1/2 must separate:
  non-interactive disabled/locked content card
  native disabled button-card
  aria-disabled plugin-managed card, if needed
```

### Risk 5 — Media Slot / Content Slot Scope

Card has media, title, subtitle, body, and actions slots.

Disposition:

```txt
Phase 1 must define which slots are in v3.5.3 scope and which are deferred
(supporting text, media ratio, action buttons, thumbnails, rich content).
```

### Risk 6 — `card` Naming And `.ax-*` Consistency

Card baseline uses `.card`, not `.ax-card`.

Disposition:

```txt
Do not rename during v3.5.3. Record as possible v3.5.x naming-sweep
input if needed. Avoid adding local aliases unless explicitly approved.
```

---

## §5 — Expected Phase 0 Report Shape

Create:

```txt
docs/v3.5.3/CARD-PHASE-0-REPORT.md
```

Suggested sections:

```txt
§0  Phase 0 framing
§1  Authoritative inputs
§2  Baseline inventory
§3  Public specimen inventory
§4  WordPress block bridge inventory
§5  Dependency profile / consumer-state
§6  Card semantics: static container vs interactive control
§7  Risks and open questions
§8  Phase 1 entry conditions
§9  G1-G10 applicability
§10 Non-goals
§11 Verdict
```

---

## §6 — Explicit Non-Goals

Phase 0 plan and report do not:

```txt
- edit components.css
- edit blocks.css
- edit style-guide.html
- edit BACKLOG.md
- edit CHANGELOG.md
- edit ROADMAP.md
- edit CURRENT-STATE.md
- edit NEXT-SESSION.md
- create lab/modules/card/
- create CARD-SPEC-AUDIT.md
- create CARD-MEASUREMENT-AUDIT.md
- create CARD-WP-MAPPING.md
- create lab-card.css
- create lab-card-pattern.html
- implement ripple
- implement Lab Preview Routes
- rename .card to .ax-card
```

---

## §7 — Validation Plan

After creating the Phase 0 report:

```powershell
python tools\validators\validate_theme_pilot.py
```

Expected:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

Also verify:

```txt
CARD-PHASE-0-REPORT.md exists
No lab/modules/card/ directory exists
No baseline/public files changed
NEXT-SESSION.md untouched
CURRENT-STATE.md untouched
```

---

## §8 — Approval Gate

Phase 0 report execution is blocked until this plan is approved.

Approved execution means:

```txt
Create CARD-PHASE-0-REPORT.md only.
Do not create Card module files.
Do not edit baseline/public/state/handoff files.
Run validator.
Report findings and Phase 1 routing questions.
```

---

## §9 — One-Line Summary

```txt
Card #9 Phase 0 should inventory the baseline Card component, the existing
core/group card bridge in blocks.css, and the narrower ripple consumer-state
for interactive/action card surfaces, while preserving Card as a Component
Full-Spec module with no baseline edits and no .card → .ax-card rename.
```
