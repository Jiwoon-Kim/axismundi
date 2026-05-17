# Axismundi v3.5.3 — Card #9 Phase 0 Report

> **Status**: Phase 0 inventory and ontology framing.
> **Date**: 2026-05-16
> **Component**: Card #9
> **Category**: Component Full-Spec
> **Scope**: Documentation-only. No baseline, public, module, state, or handoff edits.

---

## §0 — Phase 0 Framing

Card #9 is the third Wave 1 Component Full-Spec candidate after:

```txt
v3.5.1 Button #1       DONE
v3.5.2 Icon button #2  DONE
v3.5.3 Card #9         ENTERING
```

Card differs from the first two Wave 1 components:

```txt
Button / Icon button:
  Action surfaces; ripple is a direct designed target.

Card:
  Container surface; base visual cards do not need ripple.
  Interactive/action card surfaces may become ripple consumers later.
```

Phase 0's central task is therefore to separate:

```txt
base static card
interactive card as native control
card action slot composition
WordPress core/group card bridge
```

---

## §1 — Authoritative Inputs

```txt
Framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/33-COMPONENT-INVENTORY.md
  docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md

Phase plan:
  docs/v3.5.3/CARD-PHASE-0-PLAN.md

Baseline/public:
  products/reference-implementations/axismundi-lab/stylesheets/components.css §5 Card
  products/reference-implementations/axismundi-lab/style-guide.html #components-card
  products/reference-implementations/axismundi-lab/stylesheets/blocks.css §8 Group + card style variants

Precedents:
  docs/v3.5.1/BUTTON-PHASE-0-REPORT.md
  docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md
  products/reference-implementations/axismundi-lab/modules/button/docs/
  products/reference-implementations/axismundi-lab/modules/icon-button/docs/
  products/reference-implementations/axismundi-lab/modules/chip/docs/
```

Framework classification:

```txt
TOC row:        #9
TOC group:      Containers
CSS section:    components.css §5
Public anchor:  #components-card
Category:       Component Full-Spec
Current status: TODO
Target module:  card/
Dependency:     Consumer
Provider deps:  ripple/ (action surfaces)
Wave:           1
```

---

## §2 — Baseline Inventory

`components.css §5 Card` currently defines the visual primitive:

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
.card__media > img
.card__media > video
.card__title
.card__subtitle
.card__actions
```

Baseline contract:

```txt
Variants:
  filled
  elevated
  outlined

Shape:
  --comp-card-radius

Padding:
  --comp-card-padding

Typography:
  body-large host
  title-large title
  body-medium subtitle

Slots:
  media
  title
  subtitle
  actions

Interaction:
  .card--interactive
  focus-visible outline
  elevated hover level1 -> level2
  optional has-state-layer composition

Disabled:
  Pattern B whole-container opacity 0.38
  native :disabled selector
  aria-disabled selector
```

Phase 0 verdict:

```txt
Baseline is mature enough for Phase 1 audit.
No baseline mutation is authorized.
```

---

## §3 — Public Specimen Inventory

`style-guide.html #components-card` contains:

```txt
Variants — rest:
  <article class="card card--filled">
  <article class="card card--elevated">
  <article class="card card--outlined">

Interactive:
  <button class="card card--elevated card--interactive has-state-layer" type="button">

Disabled:
  <article class="card card--elevated" aria-disabled="true">
```

Important semantics:

```txt
Non-interactive cards are articles.
Interactive action card specimen is a native button with type="button".
Disabled content card uses aria-disabled on article, not native disabled.
```

This already follows the visible-control principle for the only live interactive specimen.

Gaps to audit in Phase 1:

```txt
- no filled interactive specimen
- no outlined interactive specimen
- no media slot specimen
- no actions slot specimen
- no native disabled button-card specimen
- no WordPress core/group specimen in public style guide
```

---

## §4 — WordPress Block Bridge Inventory

`blocks.css §8` already includes a Card bridge for WordPress core/group:

```txt
.wp-block-group.is-style-card-filled
.wp-block-group.is-style-card-elevated
.wp-block-group.is-style-card-outlined
```

The file comment states:

```txt
Card style variants — registered via register_block_style().
```

Phase 0 classification:

```txt
This is an intentional theme/public bridge, not accidental scaffolding.
```

Reason:

```txt
PUBLIC-SURFACE-CHARTER §3.4 and PROMOTION-CRITERIA §4 assign
WordPress block style variations / register_block_style() style mapping
to theme-can territory. Card's core/group bridge is exactly that shape:
theme-level styling of existing core block output, not plugin runtime.
```

Current bridge state:

```txt
Bridge:       CURRENT partial
Surface:      core/group
Variants:     filled / elevated / outlined
Behavior:     visual only
Missing:      media/action semantics, interactive card semantics, disabled
              examples, registration documentation, style-guide specimen
```

Risk note:

```txt
blocks.css comment says "Mirror .ax-card / .card--filled / ...",
but the actual baseline selector is .card, not .ax-card.
This is documentation drift, not a runtime issue. Record for Phase 1.
```

---

## §5 — Dependency Profile / Consumer-State

### §5.1 — Card Baseline

```txt
components.css §5 Card baseline            CURRENT
components.css §0 state-layer foundation   CURRENT for interactive specimens
blocks.css core/group card bridge          CURRENT partial
```

### §5.2 — Ripple

Phase 0 chooses Option A from the plan:

```txt
Base visual Card:
  ripple/ = NONE

Interactive/action Card:
  ripple/ = CANDIDATE, deferred to Ripple v2
```

Rationale:

```txt
BACKLOG #26 records that Card action is not in the current 7-selector
lab-ripple allowlist. The v3.5.0 matrix listed Card action as a possible
ripple consumer, but Axismundi's consumer-state rules require evidence
of a current or explicitly designed dependency before upgrading it to TARGET.

Therefore Card action/interactive remains CANDIDATE until Ripple v2 or a
separate design decision promotes it.
```

Do not remove Card from the broader ripple discussion, but do not classify base Card as ripple-dependent.

### §5.3 — Icon-System Via Composition

Card itself is not a direct icon-system consumer.

However, `card__actions` commonly composes:

```txt
<button class="ax-button is-text">...</button>
<button class="ax-icon-button">...</button>
```

Dependency framing:

```txt
icon-system/ = CURRENT conditional via composition
```

Meaning:

```txt
Card does not own glyph loading, icon font policy, or icon picker UX.
If a Card action slot contains Icon button, the nested Icon button remains
the icon-system consumer. Card owns the slot/container semantics.
```

---

## §6 — Card Semantics

Card has two different semantic modes:

```txt
Static content card:
  article / section / div depending on content context
  no click handler
  no role=button
  no tabindex for fake interaction

Interactive action card:
  button type="button" for actions
  a href="..." for navigation only
  has-state-layer may be used
  card--interactive marks visual affordance
```

Anti-pattern:

```html
<article class="card card--interactive" tabindex="0" role="button">
```

Phase 1 must make this a first-class SPEC and WP-MAPPING rule.

---

## §7 — Risks And Open Questions

### Risk 1 — Ripple Consumer-State Ambiguity

Card appears in the v3.5.0 ripple consumer graph, but v3.5.1 later clarified Card action is not in the current allowlist.

Disposition:

```txt
Base Card = NONE
Interactive/action Card = CANDIDATE
```

Do not promote to TARGET without a Ripple v2 or Card action design decision.

### Risk 2 — Existing Blocks.css Bridge Could Be Missed

Card already has a `core/group` visual bridge.

Disposition:

```txt
Phase 1 WP-MAPPING must treat this as CURRENT partial bridge, not future-only recommendation.
```

### Risk 3 — Interactive Card Semantics

Clickable cards are often implemented as clickable articles/divs.

Disposition:

```txt
Phase 1 must enforce native button/link semantics and reject fake controls.
```

### Risk 4 — Disabled Pattern B Split

Baseline supports whole-container opacity via Pattern B and both native/aria selectors.

Disposition:

```txt
Phase 1/2 should split:
  - locked non-interactive content card with aria-disabled
  - native disabled button-card
  - aria-disabled plugin-managed interactive card, if needed
```

### Risk 5 — Slot Scope

Card owns media/title/subtitle/body/actions slots, but not all slot combinations need v3.5.3 implementation.

Disposition:

```txt
Phase 1 must decide Phase 2 specimen scope:
  variants
  media slot
  action slot
  interactive card
  disabled card
```

### Risk 6 — `.card` Naming

Baseline uses `.card`, while v3.5.0 naming discipline may eventually prefer `.ax-card`.

Disposition:

```txt
Do not rename in v3.5.3.
Record as possible input to a future naming sweep if the broader sweep wants it.
```

### Risk 7 — Blocks.css Comment Drift

`blocks.css` says `.ax-card`, but actual selectors are `.card`.

Disposition:

```txt
Phase 1 should record as documentation drift. Do not edit blocks.css during Phase 0.
```

---

## §8 — Phase 1 Entry Conditions

Phase 1 should create the standard Component Full-Spec audit trio under:

```txt
products/reference-implementations/axismundi-lab/modules/card/docs/
```

Planned docs:

```txt
CARD-SPEC-AUDIT.md
CARD-MEASUREMENT-AUDIT.md
CARD-WP-MAPPING.md
```

Required Phase 1 decisions:

```txt
1. Card variants and slot scope.
2. Static card vs interactive card semantic contract.
3. core/group block bridge classification and gaps.
4. base Card ripple NONE vs action Card CANDIDATE.
5. icon-system via composition only.
6. disabled Pattern B specimen split.
7. .card naming / blocks.css comment drift disposition.
```

---

## §9 — G1-G10 Applicability

Card is Component Full-Spec, so G1-G10 apply:

```txt
G1 validator passes                 applicable
G2 baseline untouched               applicable
G3 publish runs cleanly             applicable if publish surface changes
G4 module artifacts present         Phase 2
G5 changelog entry                  Phase 5
G6 static visual QA                 Phase 3
G7 real controls                    especially for interactive cards
G8 native semantics                 button for action, anchor for navigation
G9 WCAG SC accuracy                 measurement audit required
G10 3-doc audit pattern             Phase 1
```

G11-G26 do not directly apply because Card is not an Interaction Runtime, Record, Plugin-territory Mapping, or Infrastructure Provider.

---

## §10 — Non-Goals

Phase 0 does not:

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

## §11 — Verdict

```txt
Card #9 is ready for v3.5.3 Phase 1 planning.

Classification:
  Component Full-Spec

Current status:
  TODO

Dependency profile:
  components.css §5 Card baseline            CURRENT
  components.css §0 state-layer foundation   CURRENT for interactive card
  blocks.css core/group card bridge          CURRENT partial
  ripple/                                    NONE for base Card
                                             CANDIDATE for action/interactive Card
  icon-system/                               CURRENT conditional via composition

Key Phase 1 work:
  audit the existing core/group bridge, lock static-vs-interactive card
  semantics, and decide v3.5.3 slot/specimen scope.
```

One-line summary:

```txt
Card #9 Phase 0 confirms Card as the next Component Full-Spec module,
with mature baseline variants, an intentional current partial WordPress
core/group bridge, base-card ripple NONE, action-card ripple CANDIDATE,
and icon-system only through composed action slots.
```
