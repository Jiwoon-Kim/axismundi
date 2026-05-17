# Icon button — Measurement Audit (v3.5.2 Phase 1)

> **Status**: Phase 1 measurement body authored. Implementation not started.
> **Component**: Icon button #2
> **Companion docs**: `ICON-BUTTON-SPEC-AUDIT.md`, `ICON-BUTTON-WP-MAPPING.md`

---

## §1 — Why This Audit Exists

Icon button is the first Wave 1 component whose visible content is always supplied by `icon-system/`.

This audit verifies the measurable contract across:

```txt
container geometry
touch target size
icon glyph size and hardening
variant measurements
selected/disabled state measurement effects
WCAG target-size citations
deferred size/ripple work
```

No CSS or token values are changed by this audit.

---

## §2 — Baseline Measurement Sources

```txt
Container source:
  components.css §3 .ax-icon-button

State-layer source:
  components.css §0 .has-state-layer

Glyph source:
  icons.css §1 .material-symbols-rounded
  icons.css §5 .ax-icon-button > .material-symbols-rounded

Public specimens:
  style-guide.html #components-icon-button
```

---

## §3 — Current Values

| Measurement | Current value/source | Verdict |
|---|---|---|
| Nominal container width | `--comp-button-height` = 40px | PASS |
| Nominal container height | `--comp-button-height` = 40px | PASS |
| Minimum touch width | `--comp-touch-target` = 48px | PASS |
| Minimum touch height | `--comp-touch-target` = 48px | PASS |
| Radius | full corner radius | PASS |
| Icon glyph size | 24px via Material Symbols / icon-system integration | PASS |
| Static state-layer | components.css §0 pseudo-element | PASS |
| Outlined stroke | current outlined border token | PASS for audit; verify visually in Phase 3 |

Container/touch distinction:

```txt
The visible icon button container is 40px, but the target box expands to
48px through min-width/min-height. The WCAG target-size assessment uses
the interactive target, not just the painted container.
```

---

## §4 — Icon Glyph Measurement

Phase 0 flagged a possible mismatch:

```txt
components.css §3:
  .ax-icon-button > svg,
  .ax-icon-button > .ax-icon

style-guide.html:
  .ax-icon-button > .material-symbols-rounded.notranslate
```

Phase 1 measurement resolves it:

```txt
icons.css §1:
  .material-symbols-rounded {
    font-size: 24px;
    line-height: 1;
    user-select: none;
    -webkit-user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
  }

icons.css §5:
  .ax-icon-button > .material-symbols-rounded {
    font-size: 24px;
    line-height: 1;
  }
```

Verdict:

```txt
Direct Material Symbols spans inside .ax-icon-button are covered by
icon-system integration. No baseline selector amendment is required for
v3.5.2 Phase 1.
```

---

## §5 — Variant Measurement Matrix

| Variant | Container | Icon | Selected state | Disabled state | Phase 1 verdict |
|---|---|---|---|---|---|
| Filled | 40px visible / 48px target | 24px | primary/on-primary retained | Pattern A disabled | PASS |
| Tonal | 40px visible / 48px target | 24px | secondary-container/on-secondary-container | Pattern A disabled | PASS |
| Outlined | 40px visible / 48px target | 24px | selected container/border | Pattern A disabled | PASS |
| Standard | 40px visible / 48px target | 24px | selected transparent/tonal text | transparent disabled exception | PASS |

State-layer measurement:

```txt
Static state-layer lives in components.css §0 and does not change
container geometry. Animated ripple is deferred and not measured here.
```

---

## §6 — WCAG SC Accuracy

### §6.1 — SC 2.5.8 Target Size (Minimum) AA

```txt
Requirement:
  24px minimum target size

Icon button:
  target width/height = 48px via --comp-touch-target

Verdict:
  PASS — 48 >= 24
```

### §6.2 — SC 2.5.5 Target Size (Enhanced) AAA

```txt
Requirement:
  44px enhanced target size

Icon button:
  target width/height = 48px via --comp-touch-target

Verdict:
  PASS — 48 >= 44
```

### §6.3 — Positive Finding Versus Button #1

Button #1's nominal height was 40px, so Button v3.5.1 recorded:

```txt
SC 2.5.8 AA:  PASS
SC 2.5.5 AAA: NOT met for the 40px button height
```

Icon button differs:

```txt
SC 2.5.8 AA:  PASS
SC 2.5.5 AAA: PASS
```

Reason:

```txt
.ax-icon-button uses a 40px visible container with a 48px target via
--comp-touch-target. The enhanced 44px criterion is satisfied by the
interactive target.
```

This is a positive Phase 1 finding and should be carried into Phase 3 visual QA.

---

## §7 — Deferred Measurement Items

| Item | Reason | Routing |
|---|---|---|
| XS / S / M / L / XL size set | Current baseline exposes default-size contract only | Future baseline expansion release |
| Animated ripple dimensions/timing | Current ripple not wired to Wave 1 | BACKLOG #25 / #27 |
| Public SVG snippet correction | Documentation mismatch, not measurement | Candidate BACKLOG #28 |
| Cross-browser pixel QA | Requires visual/static QA | Phase 3 |

---

## §8 — Measurement Verdict

| # | Criterion | Verdict | Notes |
|---:|---|:---:|---|
| 1 | Container metrics recorded | PASS | 40px visible / 48px target |
| 2 | Glyph sizing source recorded | PASS | icon-system integration covers Material Symbols |
| 3 | WCAG SC accuracy | PASS | SC 2.5.8 AA and SC 2.5.5 AAA both met |
| 4 | Deferred items explicit | PASS | sizes/ripple/public snippet routed |
| 5 | No baseline change recommended | PASS | audit-only |

---

## §9 — Cross-References

```txt
Companion docs:
  ./ICON-BUTTON-SPEC-AUDIT.md
  ./ICON-BUTTON-WP-MAPPING.md

Phase docs:
  docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md
  docs/v3.5.2/ICON-BUTTON-PHASE-1-PLAN.md

Measurement precedents:
  ../button/docs/BUTTON-MEASUREMENT-AUDIT.md
  ../chip/docs/CHIP-MEASUREMENT-AUDIT.md

Icon-system:
  ../icon-system/docs/ICON-SYSTEM-AUDIT.md
  ../icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md
  ../icon-system/docs/ICON-FONT-POLICY.md
```

WCAG references:

```txt
SC 2.5.8 Target Size (Minimum) AA
SC 2.5.5 Target Size (Enhanced) AAA
```

---

## §10 — What This Audit Does NOT Do

- Does not modify `components.css`.
- Does not modify `icons.css`.
- Does not modify `tokens.css`.
- Does not modify `style-guide.html`.
- Does not add XS / S / M / L / XL size variants.
- Does not implement or measure animated ripple.
- Does not change icon font loading or Material Symbols policy.
- Does not validate final rendered screenshots; Phase 3 owns visual QA.

---

## §11 — v3.5.13 Size-Matrix Measurement Addendum

Current measured baseline:

```txt
Visual container: 40px
Minimum target:   48px
Glyph:            24px
SC 2.5.8 AA:      PASS
SC 2.5.5 AAA:     PASS for effective 48px target
```

Required v3.5.13 Phase 2/3 measurement matrix:

```txt
XS / S / M / L / XL:
  visual container size
  effective hit target
  glyph size
  selected/unselected geometry
  disabled geometry
```

Acceptance condition:

```txt
If visual size drops below 44px, the effective hit target must still be
measured separately. Report visual box and target box independently.
```
