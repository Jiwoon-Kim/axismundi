# Chip Measurement Audit — v3.4.9

> Bucket: E (Component module — measurement audit)
> Closes BACKLOG #4 (Chip Measurement Audit).
> Companion document to `CHIP-SPEC-AUDIT.md`.
>
> Phase 1 — skeleton. Final measurement decisions land in Phase 3.

## §1 — Why this audit exists

BACKLOG #4 was opened when chip rendering was rough and the M3 §14 measurement table had not been confirmed against the Axismundi baseline. The audit is now feasible because:

1. The chip baseline at `components.css §11` is mature (118 lines, 7 rule blocks, with explicit measurement comments).
2. Real Material Symbols icon rendering exists (post-v3.4.4 icon-system module) for visual size comparison against M3's 18dp chip-icon spec.
3. The avatar 24dp exception is fully wired (`.chip__leading-icon.ax-avatar` selector-skip pattern).

This audit's job is **confirmation + deviation record + token alignment review**, not "fix the chip measurements". The baseline is already correct in most respects.

## §2 — M3 §14 measurement table (canonical reference)

Per Material Design 3 §14 Chips spec:

| Property | M3 spec value | Notes |
|---|---|---|
| Container height | **32dp** | All four variants |
| Corner radius | **8dp** (corner-small token) | All variants |
| Container padding (no icon) | **16dp** horizontal | Label-only chip |
| Container padding (with leading or trailing icon) | **8dp** between icon and label | M3 §14.2-14.5 |
| Leading icon size | **18dp** | All variants when icon is present |
| Trailing icon size | **18dp** | Same as leading |
| Avatar leading size (input chip) | **24dp** | M3 §14.5 — input chip exception |
| Close affordance interactive target | Material touch convention ≥ 48dp | NOT a WCAG-blocking requirement; see CHIP-SPEC-AUDIT.md §4.3 for WCAG framing |
| Label typography | M3 label-large | family / size / line-height / weight / tracking |
| Outline width (assist/filter/input rest) | 1dp | M3 §14.2/14.3/14.5 |
| Hover/Focus/Press state layer opacity | Per state layer spec | See M3 §State Layer |

## §3 — Axismundi current values (extracted from baseline)

| Property | Axismundi value | Source line | M3 alignment |
|---|---|---|:---:|
| Container height | `32px` (`--_chip-h: 32px`) | L1627 | ✓ exact |
| Corner radius | `var(--md-sys-shape-corner-small)` | L1642 | ✓ token-driven (resolves to 8px via tokens.css) |
| Container padding | `var(--space-md)` horizontal | L1640 | ⚠ space-md is 16dp/16px in current tokens.css; matches M3 no-icon padding. With-icon padding is governed by `gap: var(--space-sm)` (L1637, 8px) between icon and label. |
| Leading icon size | `18px` (`--_chip-icon: 18px`) | L1632, applied L1685-L1686 | ✓ exact |
| Trailing icon size | `18px` (same variable) | L1685-L1686 | ✓ exact |
| Avatar leading size | `.ax-avatar.is-size-xs` → 24px | preserved via selector skip pattern L1676-L1678 | ✓ exact (with exception) |
| Close affordance target | 18px visible icon, parent slot size = 18px | L1685-L1686 | ⚠ visible 18px does not prove ≥24dp interactive target |
| Label typography | `--md-sys-typescale-label-large-*` (family / size / line-height / weight / tracking) | L1647-L1651 | ✓ token-driven |
| Outline width (assist/filter/input) | `1px solid var(--md-sys-color-outline-variant)` with `outline-offset: -1px` | L1694-L1695 | ✓ exact (M3 spec: 1dp) |
| State layer | `has-state-layer` mechanism, no chip-specific override | (outside §11) | ⚠ inherits global mechanism; not chip-spec'd |

## §4 — Deviation analysis

### §4.1 No-deviation items (token-aligned or exact)

These match M3 §14 with no further action:

- Container height (32px)
- Corner radius (corner-small token → 8px)
- Leading/trailing icon size (18px)
- Avatar exception (24px via `.ax-avatar.is-size-xs`)
- Outline width (1px) and offset (-1px)
- Label typography (label-large token family, fully)

### §4.2 Tokenization decisions (NOT deviations, by design)

Two values are NOT tokenized despite measurement-like roles. Both have **explicit rationale comments in baseline code**:

| Value | Why not tokenized | Source line |
|---|---|---|
| `--_chip-h: 32px` | Component-private CSS variable (underscore prefix convention). The 32dp is a chip-specific measurement that does NOT belong in a global token. Adding a global `--ax-chip-height` would tempt reuse outside chip. | L1627 + comment style |
| `--_chip-icon: 18px` | Same reasoning, with extra emphasis: "Component-specific literal — NOT tokenized. Only chip uses 18px; adding a global token would tempt reuse where 20px/24px is correct." | L1628-L1631 (explicit comment block) |

Both decisions are **correct** under the Axismundi token-discipline rule: *tokens encode design decisions that span multiple components; component-private values stay private.* No action.

### §4.3 Padding interpretation note (potential deviation, requires Phase 2 verification)

M3 §14 distinguishes:

- Label-only chip: 16dp horizontal padding around the label
- Chip with leading icon: 16dp before icon + 8dp between icon and label + 16dp after label
- Chip with trailing icon: 16dp before label + 8dp between label and icon + 16dp after icon

The Axismundi baseline uses:

```css
.chip {
  padding-inline: var(--space-md);   /* 16px both sides */
  gap: var(--space-sm);              /* 8px between slot elements */
}
```

This is a unified rule that produces **identical** results to the M3 spec table:

| Chip shape | Effective padding | M3 spec |
|---|---|---|
| Label only | 16 / label / 16 | ✓ 16dp / label / 16dp |
| Leading icon + label | 16 / icon / 8 / label / 16 | ✓ 16dp / icon / 8dp / label / 16dp |
| Label + trailing icon | 16 / label / 8 / icon / 16 | ✓ 16dp / label / 8dp / icon / 16dp |
| Leading + label + trailing | 16 / icon / 8 / label / 8 / icon / 16 | ✓ 16dp / icon / 8dp / label / 8dp / icon / 16dp |

**Verdict for §4.3**: not a deviation. The flexbox `gap` + `padding-inline` combination is a cleaner CSS expression of the same spec table.

### §4.4 Open deviation — Close affordance target size

The single open measurement question is the input chip's close affordance interactive target. This is documented in detail in `CHIP-SPEC-AUDIT.md §4.3` with precise WCAG citation:

```
WCAG 2.2 SC 2.5.8 Target Size (Minimum) — Level AA — 24 × 24 CSS pixels
WCAG 2.2 SC 2.5.5 Target Size (Enhanced) — Level AAA — 44 × 44 CSS pixels
Material Design touch-friendly convention — ~48dp effective hit area
```

Baseline visible icon is 18px. **18px visible icon is not enough to prove an adequate interactive target.** Resolution belongs in Phase 2 implementation (`lab-chip.css`) and is recorded in `CHIP-SPEC-AUDIT.md §4.3` as Options A/B/C.

This audit does NOT pre-decide the Phase 2 outcome. It records the gap as a known deviation pending resolution.

**Phase 2 dimensions (recorded 2026-05-15)** — Option B primary + Option A-lite boost:

| Dimension | Value | WCAG / Material reference |
|---|---|---|
| Visible close glyph | 18 × 18 CSS px | matches baseline `--_chip-icon` |
| Button container | 24 × 24 CSS px | WCAG SC 2.5.8 Level AA — met on all pointer types |
| Hit area on coarse pointer | ~44 × 44 CSS px (`::before` `inset: -10px` on 24×24 button) | WCAG SC 2.5.5 Level AAA + Material touch convention — met on coarse pointer |
| Button border-radius | 50% | Visual affordance — circular touch target |

Implementation lives in `lab-chip.css §2`. The `::before` expansion is invisible (no content, no background) — purely a hit-target extension.

## §5 — Token alignment summary

| Measurement | Token used | Resolves to | M3 |
|---|---|---|:---:|
| Height | `--_chip-h` (private) | 32px | ✓ |
| Corner radius | `--md-sys-shape-corner-small` | 8px (via tokens.css) | ✓ |
| Padding-inline | `--space-md` | 16px | ✓ |
| Gap (slot spacing) | `--space-sm` | 8px | ✓ |
| Icon size | `--_chip-icon` (private) | 18px | ✓ |
| Avatar size | `--ax-avatar.is-size-xs` derived | 24px | ✓ |
| Label font | `--md-sys-typescale-label-large-font` | (family chain) | ✓ |
| Label size | `--md-sys-typescale-label-large-size` | (typescale) | ✓ |
| Label line-height | `--md-sys-typescale-label-large-line-height` | (typescale) | ✓ |
| Label weight | `--md-sys-typescale-label-large-weight` | (typescale) | ✓ |
| Label tracking | `--md-sys-typescale-label-large-tracking` | (typescale) | ✓ |
| Outline color | `--md-sys-color-outline-variant` | (color scheme) | ✓ |
| Motion duration | `--md-sys-motion-curve-fast-effects-duration` | (motion token) | ✓ |
| Motion easing | `--md-sys-motion-curve-fast-effects` | (motion token) | ✓ |
| Selected bg | `--md-sys-color-secondary-container` | (color scheme) | ✓ |
| Selected text | `--md-sys-color-on-secondary-container` | (color scheme) | ✓ |
| Disabled bg | `color-mix(..., 10%)` (Pattern A) | computed | ✓ |
| Disabled text | `color-mix(..., 38%)` (Pattern A) | computed | ✓ |
| Disabled outline | `color-mix(..., 12%)` (Pattern A) | computed | ✓ |

**Conclusion**: 19 of 20 measurement properties are token-driven. The two literals (`32px`, `18px`) have explicit rationale comments in baseline.

## §6 — Recommendations

### §6.1 No baseline changes recommended for v3.4.9

The measurement audit confirms the baseline is well-aligned to M3 §14 spec. No changes to `components.css §11 Chip` recommended at v3.4.9.

### §6.2 BACKLOG #4 closes with v3.4.9 release

Per the original BACKLOG #4 entry:

> Measurement audit triggered now that real Material Symbols chip rendering exists for comparison against M3's 32dp/8dp/18dp spec.

The comparison is complete. Findings: all primary measurements are token-driven or component-private with documented rationale. The only open measurement question is the input chip close affordance target size, which is recorded as a Phase 2 implementation decision and does NOT block BACKLOG #4 closure.

**BACKLOG #4 marked closed by v3.4.9 release** (CHANGELOG entry records this; BACKLOG.md Closed items table updated).

### §6.3 Recommendations carried forward to Phase 2

- Resolve input chip close affordance target size per `CHIP-SPEC-AUDIT.md §4.3` Options A/B/C decision.
- Document the chosen option in this measurement audit's §4.4 with the final dimension (e.g., "Phase 2 chose Option B: 48px button target; close icon remains 18px visible inside the button").
- No other measurement changes anticipated.

### §6.4 Recommendations carried forward to BACKLOG #23

Elevated chip variants (BACKLOG #23) will introduce new measurement properties — elevation tokens, hover/focus elevation transitions. When that BACKLOG item is executed, a small follow-up section will be added to this audit (or a `CHIP-MEASUREMENT-AUDIT-v2.md` companion will be authored, format TBD at that time).

## §7 — What this audit does NOT do

- Does not modify `components.css §11 Chip` measurements.
- Does not modify `style-guide.html#components-chip` baseline specimens.
- Does not pre-decide the input chip close-affordance Phase 2 resolution.
- Does not generate the M3 tonal palette colors used in selected state (those come from the color scheme tokens which are governed by `tokens.css`, not §11 Chip).
- Does not address elevated variant measurements (BACKLOG #23).
- Does not address chip arrangement / spacing within chip groups (separate question, not §14 spec).
