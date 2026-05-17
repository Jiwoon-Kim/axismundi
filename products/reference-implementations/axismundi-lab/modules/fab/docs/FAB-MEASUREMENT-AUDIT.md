# FAB Family — Measurement Audit (v3.5.5 Phase 1)

> **Status**: Phase 1 measurement body authored. Implementation not started.  
> **Component family**: FAB #3 + Extended FAB #4  
> **Companion docs**: `FAB-SPEC-AUDIT.md`, `FAB-WP-MAPPING.md`

---

## §0 — Measurement Status

This audit records the measurable contract of the current FAB family baseline.

No CSS or token values are changed by this audit.

---

## §1 — Source Inventory And Measurement Method

Sources:

```txt
components.css §15 FAB
components.css §16 Extended FAB
style-guide.html #components-fab
style-guide.html #components-fab-extended
tokens.css for shape/elevation/type/icon tokens
```

Method:

```txt
Record current baseline values first.
Compare to WCAG target-size requirements.
Record M3 naming/coverage deviations as deferrals, not implementation work.
```

---

## §2 — FAB Container Sizes

| Variant | Class | Container | Icon | Shape | Verdict |
|---|---|---:|---:|---|---|
| Default | `.ax-fab` | 56px | 24px | 16px | PASS |
| Medium | `.ax-fab.is-medium` | 80px | 28px | 20px | PASS |
| Large | `.ax-fab.is-large` | 96px | 36px | 28px | PASS |

Current baseline truth:

```txt
Axismundi exposes 56 / 80 / 96.
```

Deviation note:

```txt
If comparing against an M3 source that names 40dp as small FAB, record that
Axismundi does not currently expose a 40px FAB. Phase 1 does not invent it.
```

---

## §3 — Extended FAB Container Metrics

| Measurement | Current value | Verdict |
|---|---:|---|
| Height | 56px | PASS |
| Min width | 80px | PASS |
| Inline padding | 16px L/R | PASS |
| Gap | 8px | PASS |
| Icon | 24px | PASS |
| Shape | 16px | PASS |
| Typography | title-medium | PASS |

Extended FAB scope:

```txt
Static label-bearing primitive only.
No collapse/expand measurement in v3.5.5.
```

---

## §4 — Icon Metrics And Slot Sizing

FAB icon sizes:

```txt
default: 24px
medium:  28px
large:   36px
```

Extended FAB icon:

```txt
24px
```

Dependency implication:

```txt
icon-system/ = CURRENT unconditional.
```

Current baseline selector support:

```txt
.ax-fab > svg
.ax-fab > .ax-icon
.ax-fab-extended__icon
```

Target audit snippets use Material Symbols / icon-system-forward markup.

---

## §5 — Elevation / Shape / State-Layer Metrics

Elevation:

| State | Token | Verdict |
|---|---|---|
| Rest | `--md-sys-elevation-shadow-level3` | PASS |
| Hover | `--md-sys-elevation-shadow-level4` | PASS |
| Focus | `--md-sys-elevation-shadow-level3` | PASS |
| Active | `--md-sys-elevation-shadow-level3` | PASS |
| Disabled | `--md-sys-elevation-shadow-level0` | PASS |

State-layer:

```txt
hover opacity:   --md-sys-state-hover-state-layer-opacity
focus opacity:   --md-sys-state-focus-state-layer-opacity
pressed opacity: --md-sys-state-pressed-state-layer-opacity
```

Animated ripple:

```txt
not measured in v3.5.5
ripple/ remains CANDIDATE
```

---

## §6 — Disabled Metrics

Pattern:

```txt
Pattern A control-level disabled treatment.
```

Current selectors:

```txt
.ax-fab:disabled
.ax-fab[aria-disabled="true"]
.ax-fab-extended:disabled
.ax-fab-extended[aria-disabled="true"]
```

Measured effects:

```txt
background color-mix on-surface 10% / transparent
foreground color-mix on-surface 38% / transparent
box-shadow level0
cursor not-allowed
pointer-events none
state-layer pseudo-element hidden
```

Semantic split:

```txt
native disabled blocks activation
aria-disabled needs integrator guard
```

---

## §7 — WCAG SC Accuracy

### §7.1 SC 2.5.8 Target Size (Minimum) AA

Requirement:

```txt
24px minimum target size
```

FAB family:

```txt
56px default FAB: PASS
80px medium FAB:  PASS
96px large FAB:   PASS
56px Extended FAB height and 80px min-width: PASS
```

### §7.2 SC 2.5.5 Target Size (Enhanced) AAA

Requirement:

```txt
44px enhanced target size
```

FAB family:

```txt
56px default FAB: PASS
80px medium FAB:  PASS
96px large FAB:   PASS
56px Extended FAB height and 80px min-width: PASS
```

Comparison note:

```txt
Button #1's 40px visible height required distinction from SC 2.5.5 AAA.
FAB's current baseline sizes all exceed 44px.
```

---

## §8 — Token Coverage

Token-driven properties:

```txt
color roles
icon size base
shape corners
elevation shadows
state-layer opacity
motion duration/easing
spacing gap/padding for Extended FAB
typography for Extended FAB label
```

Literal values that remain acceptable current baseline facts:

```txt
56px / 80px / 96px FAB container sizes
28px / 36px medium/large FAB icon sizes
2px focus outline
```

Phase 1 does not introduce new tokens.

---

## §9 — Deviations / Deferrals

| Item | Status | Disposition |
|---|---|---|
| 40px small FAB | Not present in current baseline | Record only; do not implement. |
| Extended FAB medium/large | Not present in current baseline | Defer unless future spec cycle adds. |
| Animated ripple | Not measured | Ripple CANDIDATE; defer to #25/#27. |
| Collapse/expand behavior | Not measured | Behavior candidate, out of v3.5.5. |
| Public SVG specimens | Present | SPEC/WP record gap; no Phase 1 edit. |

---

## §10 — Measurement Verdict

| # | Criterion | Status | Notes |
|---:|---|:---:|---|
| 1 | Current dimensions recorded | PASS | FAB 56/80/96, Extended FAB 56/80. |
| 2 | Elevation/state metrics recorded | PASS | level3/level4/level0 and opacity tokens. |
| 3 | Icon sizing recorded | PASS | 24/28/36 and Extended 24. |
| 4 | WCAG SC accuracy | PASS | SC 2.5.8 AA and SC 2.5.5 AAA met by current sizes. |
| 5 | Deferrals explicit | PASS | 40px small, ripple, behavior, SVG cleanup. |

---

## §11 — References

```txt
FAB-SPEC-AUDIT.md
FAB-WP-MAPPING.md
docs/v3.5.5/FAB-PHASE-0-REPORT.md
docs/v3.5.5/FAB-PHASE-1-PLAN.md
```
