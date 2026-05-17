# Card — Measurement Audit (v3.5.3 Phase 1)

> **Status**: Phase 1 measurement body authored. Implementation not started.
> **Component**: Card #9
> **Companion docs**: `CARD-SPEC-AUDIT.md`, `CARD-WP-MAPPING.md`

---

## §1 — Why This Audit Exists

Card is a container component, so measurement cannot be reduced to a single target size or control height.

This audit records:

```txt
container padding/radius
variant fill/elevation/outline
interactive focus/hover metrics
slot spacing and typography
disabled Pattern B opacity
WordPress core/group bridge parity
WCAG applicability for static vs interactive cards
```

No CSS or token values are changed by this audit.

---

## §2 — Baseline Measurement Sources

```txt
Container source:
  components.css §5 .card

State-layer source:
  components.css §0 .has-state-layer

WordPress bridge source:
  blocks.css §8 .wp-block-group.is-style-card-*

Public specimens:
  style-guide.html #components-card
```

---

## §3 — Current Values

| Measurement | Current source | Verdict |
|---|---|---|
| Container padding | `--comp-card-padding` | PASS |
| Container radius | `--comp-card-radius` | PASS |
| Filled container color | surface-container-highest style token | PASS |
| Elevated shadow | elevation level1 | PASS |
| Elevated interactive hover | elevation level2 | PASS |
| Outlined border | outline-variant token / 1px | PASS |
| Focus outline | primary token / 2px / 2px offset | PASS |
| Disabled opacity | 0.38 Pattern B | PASS |
| Media slot radius | inherits card radius top corners | PASS |
| Title typography | title-large | PASS |
| Subtitle typography | body-medium | PASS |
| Actions slot | flex row / gap / end alignment | PASS |

Card uses responsive width-flow:

```txt
No fixed width, fixed height, XS/S/M/L/XL card size scale, or grid system
is present in the baseline.
```

---

## §4 — Variant Measurement Matrix

| Variant | Fill / surface | Elevation / border | Interactive delta | Phase 1 verdict |
|---|---|---|---|---|
| Filled | filled surface | level0 | focus outline if interactive | PASS |
| Elevated | elevated surface | level1 | hover level2 if interactive | PASS |
| Outlined | surface | 1px outline | focus outline adjusted | PASS |

Baseline variant coverage is sufficient for v3.5.3 Phase 1.

Phase 2 should demonstrate all three variants in `lab-card-pattern.html`.

---

## §5 — Slot Measurement

Classed slots:

| Slot | Measurement contract | Phase 1 verdict |
|---|---|---|
| `.card__media` | block slot with negative top/side margins against card padding | PASS |
| `.card__media > img/video` | full width, block, object-cover, top radius | PASS |
| `.card__title` | title-large typography / bottom spacing | PASS |
| `.card__subtitle` | body-medium typography / secondary color / top spacing | PASS |
| `.card__actions` | flex row, wrap, gap, end alignment / top spacing | PASS |

Un-classed content:

```txt
Normal text, paragraphs, and other simple content may appear directly
inside `.card`. There is no `.card__body` baseline selector.
```

Implementation warning:

```txt
Do not invent `.card__body` in Phase 1 or Phase 2.
```

---

## §6 — Disabled Pattern B Measurement

Card disabled treatment:

```txt
.card[aria-disabled="true"],
.card:disabled {
  opacity: 0.38;
}
```

Measurement meaning:

```txt
Pattern B applies to the whole container, including media, text, and
actions slot content.
```

Three documented cases:

| Case | Markup | Measurement effect | Activation effect |
|---|---|---|---|
| Locked content card | `article[aria-disabled="true"]` | whole-card opacity | no activation exists |
| Native disabled action card | `button:disabled` | whole-card opacity | browser blocks activation |
| Plugin-managed action card | `button[aria-disabled="true"]` | whole-card opacity | plugin must block activation |

Do not substitute Button/Icon button Pattern A disabled measurements for Card.

---

## §7 — Interactive Measurement And State Layer

Interactive Card measurement:

```txt
.card--interactive:
  cursor pointer

.card--interactive:focus-visible:
  2px outline
  2px offset

.card--interactive.card--elevated:hover:
  elevation level2
```

State-layer foundation:

```txt
components.css §0 is CURRENT for interactive cards that opt into
.has-state-layer.
```

Animated ripple:

```txt
Not measured in v3.5.3.
Interactive/action Card remains ripple CANDIDATE until Ripple v2.
```

---

## §8 — WordPress Bridge Measurement

`blocks.css §8` maps core/group style variations to Card-like measurements:

```txt
.wp-block-group.is-style-card-filled
.wp-block-group.is-style-card-elevated
.wp-block-group.is-style-card-outlined
```

Phase 1 measurement verdict:

```txt
CURRENT partial parity with baseline visual variants.
```

What the bridge covers:

```txt
filled/elevated/outlined visual chrome
padding/radius
elevation/border
```

What the bridge does not cover:

```txt
native interactive semantics
media/title/subtitle/actions slot taxonomy
disabled split
runtime behavior
```

---

## §9 — WCAG SC Accuracy

### §9.1 — Static Card

```txt
Base Card is content, not a control.
SC 2.5.8 Target Size (Minimum) and SC 2.5.5 Target Size (Enhanced) are
not directly applicable to static article/section/div cards.
```

### §9.2 — Interactive Action / Navigation Card

```txt
When the whole card is a button or anchor, the whole card becomes the
target. Normal card dimensions exceed target-size minimums.
```

Primary gates:

```txt
Principle 1:
  the interactive card must be a visible control.

Principle 2:
  actions use button; navigation uses anchor.
```

### §9.3 — Nested Actions

```txt
Buttons or Icon buttons inside `.card__actions` are measured by their own
audits. Card does not re-measure their target size.
```

---

## §10 — Deferred Measurement Items

| Item | Reason | Routing |
|---|---|---|
| Fixed card size scale | Not present in baseline | Future design decision |
| Card grid/layout system | Not part of component primitive | Future pattern/layout work |
| Animated ripple | Candidate only | BACKLOG #25 / #26 |
| `.card__body` selector | Not present | Do not introduce |
| blocks.css comment drift | Documentation drift | Future cleanup if desired |
| Pixel screenshot QA | Requires rendered inspection | Phase 3 |

---

## §11 — Measurement Verdict

| # | Criterion | Verdict | Notes |
|---:|---|:---:|---|
| 1 | Container metrics recorded | PASS | padding/radius/variant chrome |
| 2 | Slot metrics recorded | PASS | classed slots + un-classed content distinction |
| 3 | Disabled Pattern B measured | PASS | whole-container opacity split |
| 4 | WP bridge measurement recorded | PASS | core/group current partial bridge |
| 5 | WCAG SC applicability accurate | PASS | static vs interactive nuance |
| 6 | Deferred items explicit | PASS | ripple/grid/body selector routed |

---

## §12 — Cross-References

```txt
Companion docs:
  ./CARD-SPEC-AUDIT.md
  ./CARD-WP-MAPPING.md

Phase docs:
  docs/v3.5.3/CARD-PHASE-0-REPORT.md
  docs/v3.5.3/CARD-PHASE-1-PLAN.md

Measurement precedents:
  ../button/docs/BUTTON-MEASUREMENT-AUDIT.md
  ../icon-button/docs/ICON-BUTTON-MEASUREMENT-AUDIT.md
  ../chip/docs/CHIP-MEASUREMENT-AUDIT.md

Framework:
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
```

---

## §13 — What This Audit Does NOT Do

- Does not modify `components.css`.
- Does not modify `blocks.css`.
- Does not modify `tokens.css`.
- Does not modify `style-guide.html`.
- Does not add fixed Card sizes.
- Does not add `.card__body`.
- Does not implement or measure animated ripple.
- Does not register WordPress block styles.
- Does not validate final rendered screenshots; Phase 3 owns visual QA.
