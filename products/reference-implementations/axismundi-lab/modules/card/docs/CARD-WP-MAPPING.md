# Card — WordPress Mapping Audit (v3.5.3 Phase 1)

> **Status**: Phase 1 WordPress mapping body authored. Implementation not started.
> **Component**: Card #9
> **Companion docs**: `CARD-SPEC-AUDIT.md`, `CARD-MEASUREMENT-AUDIT.md`

---

## §1 — Critical Framing

Card is the first Wave 1 component with an existing WordPress block bridge before module implementation.

Current public bridge:

```txt
blocks.css §8:
  .wp-block-group.is-style-card-filled
  .wp-block-group.is-style-card-elevated
  .wp-block-group.is-style-card-outlined
```

Framework classification:

```txt
CURRENT partial bridge.
```

Reason:

```txt
PUBLIC-SURFACE-CHARTER §3.4 and PROMOTION-CRITERIA §4 classify
register_block_style() / block style variations as theme-can territory.
```

This mapping audit does not propose the bridge; it audits what already exists.

---

## §2 — Core / Block Context Inventory

| Surface | Card relevance | Phase 1 mapping |
|---|---|---|
| `core/group` | Primary existing bridge. Group can act as visual card container. | CURRENT partial via `.is-style-card-*`. |
| `core/columns` | Layout wrapper around multiple cards. | Layout context, not Card primitive. |
| `core/row` | Horizontal layout wrapper around cards/actions. | Layout context, not Card primitive. |
| `core/media-text` | Related media/content pattern. | Adjacent pattern, not Card primitive. |
| `core/query` / post template | Common card-like post listings. | Pattern/composition layer; may consume Card styles. |
| `core/buttons` / `core/button` | Nested actions in `.card__actions`. | Use Button audit contract. |
| Editor custom block | Dynamic cards / data-bound cards. | Plugin/custom-block territory. |

---

## §3 — Current Bridge Inventory

Existing selectors:

```txt
.wp-block-group.is-style-card-filled
.wp-block-group.is-style-card-elevated
.wp-block-group.is-style-card-outlined
```

What the bridge covers:

```txt
visual chrome:
  filled
  elevated
  outlined

theme-side style variation:
  core/group can be rendered as a Card-like container.
```

What the bridge does not cover:

```txt
interactive semantics
media/title/subtitle/actions slots
disabled split
dynamic behavior
custom editor UI
registration documentation
```

Known comment drift:

```txt
blocks.css says `.ax-card` and components.css §3.
Actual Card baseline is `.card` in components.css §5.
```

Disposition:

```txt
Record only in Phase 1. Do not edit blocks.css.
```

---

## §4 — Card Semantics Decision Tree

```txt
Is the card just content?
  Use <article>, <section>, or <div> depending the document context.

Does the whole card trigger an action?
  Use <button class="card card--interactive" type="button">.

Does the whole card navigate?
  Use <a class="card card--interactive" href="...">.

Does only a nested action activate?
  Use a static card container with .card__actions containing Button or
  Icon button controls.
```

Forbidden:

```html
<article class="card card--interactive" role="button" tabindex="0">
  ...
</article>
```

WordPress implication:

```txt
core/group card styles are safe for visual content cards.
Whole-card actions or navigation require markup/control decisions beyond
a simple core/group style variation.
```

---

## §5 — Theme-Can / Plugin-Should Boundary

Theme-can:

```txt
- register_block_style() for core/group card visual variants
- CSS styling for filled/elevated/outlined group cards
- static pattern composition using Card + Button/Icon button
- frontend rendering of non-dynamic card patterns
```

Plugin-should:

```txt
- dynamic card data binding
- editor controls for card entities
- custom Card block runtime
- whole-card action behavior
- drag/drop, sorting, expandable cards
- guarded aria-disabled behavior
- ActivityPub/social runtime actions
```

The theme may style a core/group as a card. It should not fake application behavior with CSS.

---

## §6 — Slot And Composition Mapping

Card-owned slots:

```txt
.card__media
.card__title
.card__subtitle
.card__actions
```

Un-classed content:

```txt
Body text and simple paragraph content live directly inside `.card`.
Do not invent `.card__body` for WordPress mapping.
```

Action composition:

```txt
.card__actions may contain:
  Button
  Icon button
```

Ownership:

```txt
Card owns the actions slot layout.
Nested controls own their own semantics, accessibility, and dependencies.
```

---

## §7 — Disabled Mapping

### §7.1 — Locked Content Card

```html
<article class="card card--elevated" aria-disabled="true">
  ...
</article>
```

Use when the card is unavailable content, not an activatable control.

### §7.2 — Native Disabled Action Card

```html
<button class="card card--elevated card--interactive" type="button" disabled>
  ...
</button>
```

Use when the whole card is an action and should be disabled by platform behavior.

### §7.3 — Plugin-Managed aria-disabled Action Card

```html
<button class="card card--elevated card--interactive" type="button" aria-disabled="true">
  ...
</button>
```

Required caveat:

```txt
aria-disabled does not block activation. Plugin/app code must guard click,
keyboard, and submit behavior.
```

---

## §8 — Ripple And State-Layer Mapping

Base Card:

```txt
ripple/ = NONE
```

Interactive/action Card:

```txt
ripple/ = CANDIDATE
```

Mapping implication:

```txt
Do not wire current lab ripple to WordPress Card surfaces.
If Ripple v2 later adopts Card action surfaces, it should handle Button,
Icon button, Card, and other consumers together.
```

State-layer:

```txt
Interactive Card may use `.has-state-layer` from components.css §0.
This is static CSS state-layer foundation, not animated ripple.
```

---

## §9 — Anti-Pattern Inventory

| Anti-pattern | Why it is wrong |
|---|---|
| `<article role="button" tabindex="0">` for whole-card action | Recreates native button behavior badly. |
| Clickable `<div class="card">` | Not keyboard/native accessible as a control. |
| `<a class="card">` for non-navigation action | Links navigate; actions need buttons. |
| `<button class="card">` wrapping nested buttons/links | Invalid/confusing nested interactive controls. |
| Disabled-looking interactive card without `disabled` or guarded `aria-disabled` | Looks unavailable but remains activatable. |
| Treating `aria-disabled` as activation blocking | It only communicates state; behavior must be guarded. |
| Inventing `.card__body` | Not a baseline slot; creates shadow API. |
| Bypassing Button/Icon button in `.card__actions` | Loses established control contracts. |
| Treating blocks.css bridge as component source authority | Source authority remains components.css §5 / module docs. |
| Ad hoc current ripple wiring | Card action ripple is CANDIDATE until Ripple v2. |

---

## §10 — ActivityPub / Social CMS Note

Future social/CMS surfaces may render:

```txt
post cards
profile cards
reply/repost/share card actions
notification cards
federated object previews
```

Phase 1 does not implement those surfaces.

Carry-forward contract:

```txt
static card for content
button card for whole-card action
anchor card for whole-card navigation
nested Button/Icon button for action rows
plugin-managed guards for dynamic disabled states
```

---

## §11 — Mapping Verdict

| # | Criterion | Verdict | Notes |
|---:|---|:---:|---|
| 1 | Core/block context inventory | PASS | core/group bridge distinguished from wrappers/patterns |
| 2 | Current bridge classification | PASS | blocks.css card styles are CURRENT partial |
| 3 | Native semantics decision tree | PASS | article/button/anchor split recorded |
| 4 | Theme/plugin boundary | PASS | CHARTER §3.4 / PROMOTION §4 applied |
| 5 | Disabled mapping | PASS | Pattern B three-way split recorded |
| 6 | Anti-pattern inventory | PASS | 10 Card-specific anti-patterns recorded |
| 7 | Dependency/ripple mapping | PASS | state-layer current, ripple candidate |

---

## §12 — Cross-References

```txt
Companion docs:
  ./CARD-SPEC-AUDIT.md
  ./CARD-MEASUREMENT-AUDIT.md

Phase docs:
  docs/v3.5.3/CARD-PHASE-0-REPORT.md
  docs/v3.5.3/CARD-PHASE-1-PLAN.md

Framework:
  docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/MODULE-STATUS-MATRIX.md

Precedents:
  ../button/docs/BUTTON-WP-MAPPING.md
  ../button/docs/BUTTON-SPEC-AUDIT.md
  ../icon-button/docs/ICON-BUTTON-WP-MAPPING.md
  ../icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
  ../chip/docs/CHIP-WP-MAPPING.md
```

---

## §13 — What This Mapping Audit Does NOT Do

- Does not modify `blocks.css`.
- Does not modify `components.css`.
- Does not modify `style-guide.html`.
- Does not register WordPress block styles.
- Does not create a custom Card block.
- Does not edit WordPress PHP or plugin code.
- Does not implement whole-card action behavior.
- Does not implement ActivityPub/social actions.
- Does not introduce `.card__body`.
- Does not implement ripple wiring.
- Does not implement Lab Preview Routes.
