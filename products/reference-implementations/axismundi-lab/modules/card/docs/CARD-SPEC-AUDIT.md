# Card — Spec Audit (v3.5.3 Phase 1)

> **Status**: v3.5.3 release closed. Phase 2 artifacts authored; Phase 3 visual QA PASS; Phase 5 mechanical close DONE.
> **Component**: Card #9
> **Category**: Component Full-Spec
> **Primary Phase 0 source**: `docs/v3.5.3/CARD-PHASE-0-REPORT.md`
> **Execution plan**: `docs/v3.5.3/CARD-PHASE-1-PLAN.md`

---

## §0 — Status / Scope

This audit promotes Card #9 into the Component Full-Spec audit lane after Button #1 and Icon button #2.

Card differs from the first two Wave 1 components:

```txt
Button / Icon button:
  Direct action controls.
  Ripple is a direct designed consumer-state topic.

Card:
  Container surface first.
  Base card is not a control.
  Interactive card is a native button or anchor variant.
```

This audit began as a documentation-only Phase 1 artifact. At release close it records the completed Phase 2 lab artifacts and Phase 3 visual QA result. Baseline/public files remain unchanged.

---

## §1 — Authoritative Inputs

```txt
Phase docs:
  docs/v3.5.3/CARD-PHASE-0-REPORT.md
  docs/v3.5.3/CARD-PHASE-1-PLAN.md

Framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/33-COMPONENT-INVENTORY.md
  docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md

Baseline/public:
  products/reference-implementations/axismundi-lab/stylesheets/components.css §5
  products/reference-implementations/axismundi-lab/stylesheets/blocks.css §8
  products/reference-implementations/axismundi-lab/style-guide.html #components-card

Sibling precedents:
  ../button/docs/BUTTON-SPEC-AUDIT.md
  ../button/docs/BUTTON-MEASUREMENT-AUDIT.md
  ../button/docs/BUTTON-WP-MAPPING.md
  ../icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
  ../icon-button/docs/ICON-BUTTON-MEASUREMENT-AUDIT.md
  ../icon-button/docs/ICON-BUTTON-WP-MAPPING.md
  ../chip/docs/CHIP-SPEC-AUDIT.md
  ../chip/docs/CHIP-MEASUREMENT-AUDIT.md
  ../chip/docs/CHIP-WP-MAPPING.md
```

---

## §2 — Baseline Card Inventory

`components.css §5 Card` defines:

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

Current baseline contract:

```txt
base:
  display block
  radius via --comp-card-radius
  padding via --comp-card-padding
  body-large typography
  surface/on-surface tokens

variants:
  filled
  elevated
  outlined

interactive:
  .card--interactive
  focus-visible outline
  elevated hover level1 -> level2
  optional .has-state-layer composition

disabled:
  Pattern B whole-container opacity 0.38
  native :disabled selector
  aria-disabled selector

slots:
  media
  title
  subtitle
  actions
```

Important body-content note:

```txt
Classed slots:
  .card__media
  .card__title
  .card__subtitle
  .card__actions

Un-classed content:
  direct text/paragraph/content children inside .card.

Forbidden in v3.5.3:
  inventing a new .card__body selector.
```

---

## §3 — Public Specimen Inventory

`style-guide.html #components-card` contains:

```txt
Static variants:
  <article class="card card--filled">
  <article class="card card--elevated">
  <article class="card card--outlined">

Interactive action card:
  <button class="card card--elevated card--interactive has-state-layer" type="button">

Disabled / locked content card:
  <article class="card card--elevated" aria-disabled="true">
```

Phase 1 verdict:

```txt
The public specimens correctly distinguish static article cards from
the one interactive button-card specimen.
```

Public gaps to address in Phase 2 pattern planning:

```txt
- media slot specimen
- actions slot specimen
- native disabled button-card specimen
- aria-disabled plugin-managed interactive card caveat
- navigation card specimen
- core/group WordPress bridge specimen or snippet
```

---

## §4 — Variant And Slot Coverage

| Area | Baseline support | Public specimen | Phase 1 verdict |
|---|---|---|---|
| Filled card | yes | yes | PASS |
| Elevated card | yes | yes | PASS |
| Outlined card | yes | yes | PASS |
| Interactive elevated card | yes | yes | PASS |
| Interactive filled/outlined card | class model supports | no | Phase 2 specimen |
| Media slot | yes | no | Phase 2 specimen |
| Title slot | yes | yes | PASS |
| Subtitle slot | yes | yes | PASS |
| Body content | un-classed content | yes | PASS; no `.card__body` |
| Actions slot | yes | no | Phase 2 specimen |
| Native disabled button-card | selector exists | no | Phase 2 specimen |
| aria-disabled content card | selector exists | yes | PASS |
| Navigation card | semantic pattern expected | no | Phase 2 specimen |

Phase 2 should demonstrate the current baseline slots without expanding the slot taxonomy.

---

## §5 — Native Semantics Decision Tree

Card semantics are context-dependent.

```txt
Is the card static content?
  Use <article>, <section>, or <div> based on document context.
  Do not add role="button".
  Do not add tabindex.
  Do not attach click handlers to the container.

Does the whole card trigger an action?
  Use <button class="card card--interactive" type="button">.

Does the whole card navigate?
  Use <a class="card card--interactive" href="...">.

Does only a nested action activate?
  Use a static card container with .card__actions containing Button or
  Icon button controls.
```

Forbidden anti-pattern:

```html
<article class="card card--interactive" role="button" tabindex="0">
  ...
</article>
```

Principle 1:

```txt
Interactive cards must be visible controls. A fake clickable article
or div is not acceptable even if it has hover styling.
```

Principle 2:

```txt
Use native button semantics for actions and native anchor semantics for
navigation. Do not recreate either behavior with ARIA.
```

---

## §6 — Disabled Pattern B Split

Card uses Pattern B disabled treatment:

```txt
whole-container opacity: 0.38
```

Phase 1 splits three cases.

### §6.1 — Non-Interactive Locked Card

```html
<article class="card card--elevated" aria-disabled="true">
  ...
</article>
```

Meaning:

```txt
The content is locked, unavailable, or inactive.
There is no activation to block.
```

### §6.2 — Native Disabled Action Card

```html
<button class="card card--elevated card--interactive" type="button" disabled>
  ...
</button>
```

Meaning:

```txt
The whole card is an action control and browser/platform behavior blocks
activation.
```

### §6.3 — aria-disabled Plugin-Managed Interactive Card

```html
<button class="card card--elevated card--interactive" type="button" aria-disabled="true">
  ...
</button>
```

Meaning:

```txt
The control exposes disabled state and receives Pattern B styling, but
aria-disabled does not block activation. The integrator/plugin must guard
event handling.
```

Do not collapse Card Pattern B into Button/Icon button Pattern A.

---

## §7 — Dependency Profile

> **v3.5.4 matrix amendment note**: this section is now aligned with
> the canonical consumer-state vocabulary introduced in
> `docs/v3.5.0/MODULE-STATUS-MATRIX.md`: base Card `ripple/` = NONE,
> action/interactive Card `ripple/` = CANDIDATE, and `icon-system/` =
> CURRENT conditional via composition.
>
> **v3.5.6 Ripple v2 alignment note**: base Card remains `ripple/` = NONE.
> Action/interactive Card is promoted from CANDIDATE to TARGET with the
> bounded variant. The stable animated ripple contract is `data-ax-ripple`
> + `window.axRipple`; it remains a progressive enhancement above
> `components.css §0` and does not change the Card baseline.

```txt
components.css §5 Card baseline            CURRENT
components.css §0 state-layer foundation   CURRENT for interactive card
blocks.css core/group card bridge          CURRENT partial
ripple/                                    NONE for base Card
                                           TARGET for action/interactive Card
icon-system/                               CURRENT conditional via composition
```

### §7.1 — Ripple

Base Card is not a ripple consumer.

Interactive/action Card was promoted to TARGET by v3.5.6 Ripple v2:

```txt
Base Card remains outside animated ripple.
Action/interactive Card uses bounded ripple as a progressive enhancement.
```

References:

```txt
BACKLOG #25 — Ripple v2 contract
BACKLOG #26 — Matrix row #36 allowlist correction
```

### §7.2 — Icon-System

Card itself does not own glyph infrastructure.

Composition-only dependency:

```txt
.card__actions may contain:
  Button
  Icon button

If Icon button appears in .card__actions, Icon button remains the direct
icon-system consumer. Card owns only the slot/container.
```

---

## §8 — WordPress Bridge Summary

Card has an existing WordPress bridge in `blocks.css §8`:

```txt
.wp-block-group.is-style-card-filled
.wp-block-group.is-style-card-elevated
.wp-block-group.is-style-card-outlined
```

Phase 1 classification:

```txt
CURRENT partial bridge.
```

Reason:

```txt
PUBLIC-SURFACE-CHARTER §3.4 and PROMOTION-CRITERIA §4 place
register_block_style() / block style variations in theme-can territory.
Card's core/group bridge is exactly that kind of theme-side mapping.
```

Known drift:

```txt
blocks.css comment mentions `.ax-card` and components.css §3.
Actual baseline is `.card` in components.css §5.
```

This audit records the drift but does not edit `blocks.css`.

Details are owned by `CARD-WP-MAPPING.md`.

---

## §9 — Exceptions And Deferred Items

| Item | Status | Disposition |
|---|---|---|
| `.card__body` selector | Not in baseline | Do not introduce in v3.5.3 |
| Animated ripple | TARGET for action Card only | Closed by Ripple v2 / BACKLOG #25 |
| Card action ripple classification | Ambiguous in old matrix | v3.5.6 promotes action Card to TARGET; base Card remains NONE |
| blocks.css comment drift | Real documentation drift | Record only; no Phase 1 edit |
| `.card` -> `.ax-card` naming | Future naming policy topic | No rename |
| Complex card grids/layouts | Out of Component Full-Spec scope | Future patterns |
| WordPress registration docs | Bridge exists but registration not authored | WP-MAPPING records gap |

---

## §10 — Phase 2 Readiness Checklist

Phase 2 can plan module implementation after Phase 1 review if:

```txt
✓ SPEC, MEASUREMENT, and WP-MAPPING docs exist
✓ native semantics decision tree is locked
✓ disabled Pattern B split is explicit
✓ blocks.css bridge is marked CURRENT partial
✓ base Card ripple NONE is explicit
✓ action Card ripple TARGET is explicit after v3.5.6 Ripple v2
✓ icon-system composition-only dependency is explicit
✓ `.card__body` invention is forbidden
✓ no baseline/public/state/handoff files were edited
```

Recommended Phase 2 deliverables:

```txt
lab-card.css
lab-card-pattern.html
```

Likely not needed:

```txt
lab-card.js
```

---

## §11 — SPEC Verdict Criteria (Phase 5 close, ALL PASS)

| # | Criterion | Phase 5 verdict | Notes |
|---:|---|:---:|---|
| 1 | Card variant coverage | PASS at audit level | filled/elevated/outlined baseline and specimens recorded |
| 2 | Native semantics contract | PASS | article/button/anchor decision tree recorded |
| 3 | Slot coverage | PASS at audit level | classed slots + un-classed body content distinction recorded |
| 4 | Dependency declarations | PASS | baseline/state-layer/blocks/ripple/icon-system profile recorded |
| 5 | Disabled semantics | PASS | Pattern B three-way split recorded |
| 6 | Phase 2 artifact completeness | PASS | `lab-card.css` + `lab-card-pattern.html` authored; no JS created |
| 7 | Static Visual QA | PASS | User-verified after Phase 2; no blocking visual issues reported |

Phase 5 release close:

```txt
v3.5.3 Wave 1 — Card #9 closes as DONE.
Baseline/public files remain unchanged.
BACKLOG #29 records deferred Card behavior patterns from the M3 guideline
cross-check: expanding, swipe, pickup/reorder, and scrolling behavior.
```

---

## §12 — G1-G10 Gate Applicability

| Gate | Applies? | Phase 5 state |
|---|:---:|---|
| G1 validator passes | yes | PASS — 1.000 / 1.000 / 1.000 / 1.000 |
| G2 baseline untouched | yes | PASS — components.css / blocks.css / style-guide.html unchanged |
| G3 publish runs cleanly | yes | N/A for Phase 5 close — no publish-surface mutation in v3.5.3 |
| G4 module artifacts present | yes | PASS — `lab-card.css` + `lab-card-pattern.html` |
| G5 changelog entry | yes | PASS — v3.5.3 entry added |
| G6 static visual QA | yes | PASS — user-verified |
| G7 real controls | yes | PASS — action card uses native button; navigation card uses anchor |
| G8 native semantics | yes | PASS — no fake clickable articles/divs |
| G9 WCAG SC accuracy | yes | PASS — MEASUREMENT records static-vs-interactive nuance |
| G10 3-doc audit pattern | yes | PASS — SPEC + MEASUREMENT + WP-MAPPING complete |

G11-G26 do not apply directly: Card is a Component Full-Spec consumer, not an Interaction Runtime, Record, Plugin, or Infrastructure provider.

---

## §13 — References

```txt
Companion docs:
  ./CARD-MEASUREMENT-AUDIT.md
  ./CARD-WP-MAPPING.md

Phase docs:
  docs/v3.5.3/CARD-PHASE-0-REPORT.md
  docs/v3.5.3/CARD-PHASE-1-PLAN.md

Framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/33-COMPONENT-INVENTORY.md
  docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md

Precedents:
  ../button/docs/BUTTON-SPEC-AUDIT.md
  ../button/docs/BUTTON-MEASUREMENT-AUDIT.md
  ../button/docs/BUTTON-WP-MAPPING.md
  ../icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
  ../icon-button/docs/ICON-BUTTON-MEASUREMENT-AUDIT.md
  ../icon-button/docs/ICON-BUTTON-WP-MAPPING.md
  ../chip/docs/CHIP-SPEC-AUDIT.md
  ../chip/docs/CHIP-MEASUREMENT-AUDIT.md
  ../chip/docs/CHIP-WP-MAPPING.md
```

---

## §14 — What This Audit Does NOT Do

- Does not edit `components.css §5`.
- Does not edit `components.css §0`.
- Does not edit `blocks.css §8`.
- Does not edit `style-guide.html #components-card`.
- Does not edit `tokens.css`.
- Does not edit `theme.json`.
- Does not edit `BACKLOG.md`.
- Does not edit `CHANGELOG.md`.
- Does not edit `ROADMAP.md`.
- Does not edit `CURRENT-STATE.md`.
- Does not edit `NEXT-SESSION.md`.
- Does not create `lab-card.css`.
- Does not create `lab-card-pattern.html`.
- Does not create `lab-card.js`.
- Does not implement Ripple v2.
- Does not implement Lab Preview Routes.
- Does not rename `.card` to `.ax-card`.
- Does not introduce `.card__body`.
