# List — Measurement Audit (v3.5.11 Phase 5 Close)

> **Status**: Phase 5 close complete.  
> **Component**: List #33  
> **Companion docs**: `LIST-SPEC-AUDIT.md`, `LIST-WP-MAPPING.md`

---

## §0 — Scope

This document audits List #33 dimensions, slot geometry, typography, state
measurements, WCAG SC applicability, and Phase 2/3/5 Playwright checks.

Phase 5 edited only `components.css` §26 to resolve List-specific token-level
color mismatches found during visual QA.

---

## §1 — Inputs Read

- `docs/v3.5.11/LIST-PHASE-0-REPORT.md`
- `components.css` §26 List
- `style-guide.html#components-list`
- M3 list specs/guidelines/accessibility pages extracted via Playwright
- `LIST-SPEC-AUDIT.md`
- Wave 1 measurement precedents:
  - Button group v3.5.10
  - Text field v3.5.7
  - Card v3.5.3

---

## §2 — Baseline Measurement Inventory

| Measurement | Current value | Source | Verdict |
|---|---:|---|---|
| Single-line row min-height | 56px | `.ax-list__item` | Matches M3 |
| Two-line row min-height | 72px | `.ax-list__item--two-line` | Matches M3 |
| Three-line row min-height | 88px | `.ax-list__item--three-line` | Matches M3 |
| Inline padding | 16px | `.ax-list__item` | Matches M3 baseline |
| Block padding | 10px | `.ax-list__item` | Supports 56px min-height |
| Slot gap | 12px | `.ax-list__item` | Matches M3 spacing intent |
| Leading/trailing icon | 24px | slot child rules | Matches M3 |
| Leading image | 56px | `.ax-list__leading-image` | Matches M3 |
| Divider height | 1px | `.ax-list__divider` | Expected |
| Segmented gap | 2px | `.ax-list--segmented` | Matches M3 segmented style |
| Container radius | corner-large | `.ax-list` | Matches baseline |
| Rest row radius | corner-extra-small | `.ax-list__item` | Baseline expressive state |
| Hover radius | corner-medium | `.ax-list__item:hover` | Baseline expressive state |
| Focus/active/selected radius | corner-large | state rules | Baseline expressive state |

---

## §3 — M3 Measurement Comparison

| M3 measurement area | Axismundi current state | Phase 5 verdict |
|---|---|---|
| 56dp one-line item | 56px min-height | PASS at audit level |
| 72dp two-line item | 72px min-height | PASS at audit level |
| 88dp three-line item | 88px min-height | PASS at audit level |
| Leading icon 24dp | 24px | PASS |
| Trailing icon 24dp | 24px | PASS |
| Leading image 56dp | 56px | PASS |
| Segmented gap | 2px | PASS |
| Supporting text clamp | two-line clamp for 3-line rows | PASS |
| Video slot | deferred | Honest deferral |
| Multi-select visuals | deferred | Honest deferral |
| Drag handle | deferred | Honest deferral |

---

## §4 — Typography

Baseline typography:

| Slot | Token family |
|---|---|
| Overline | label-small |
| Label | body-large |
| Supporting text | body-medium |
| Trailing text | label-small |

Phase 2 must verify:

- label truncates without overlapping trailing slot,
- supporting text stays one line in two-line rows,
- supporting text clamps to two lines in three-line rows,
- trailing text remains readable on narrow viewport,
- Avatar/image leading slots do not compress content below usable width.

---

## §5 — State Geometry

Baseline shape transitions:

- rest: corner-extra-small,
- hover: corner-medium,
- focus-visible: corner-large,
- active: corner-large,
- selected: corner-large,
- disabled: corner-extra-small.

This is another morphing component family, but it does not currently use
`corner-full`; v3.5.9 pill-stable correction is not directly involved.

Phase 3 Playwright should verify:

- shape radius changes are visually stable,
- focus outline remains inside dense row geometry (`outline-offset: -3px`),
- selected background and corner-large shape align,
- disabled state does not expose active/hover state-layer effects.

---

## §6 — Slot Geometry

Leading slot:

- icon: 24px,
- Avatar: composed `.ax-avatar`, expected 40px default,
- image: 56px.

Trailing slot:

- icon: 24px,
- trailing text: label-small,
- trailing icon button: composition only; not required for primitive closure.

Content slot:

- flexes to available inline space,
- `min-width: 0` prevents overflow,
- label/supporting text truncate/clamp.

Phase 2 pattern HTML should include at least one narrow-viewport stress case
for long label + trailing text.

---

## §7 — Token Comparison / Phase 3 Finding

Phase 3 review compared the M3 List token table against baseline §26. This
section records token-level full-spec coverage so G9/G10 closure does not rely
on a vague "token-driven" claim.

| M3 token row | Expected token | Baseline §26 current value | Verdict |
|---|---|---|---|
| item container color | `md.sys.color.surface` | `.ax-list { background-color: surface }` | PASS |
| segmented container color | `md.sys.color.surface` | `.ax-list--segmented { background-color: surface }` | PASS after Phase 5 patch |
| label text color | `md.sys.color.on-surface` | row `color: on-surface`; label inherits | PASS |
| supporting text color | `md.sys.color.on-surface-variant` | `.ax-list__supporting` | PASS |
| overline color | `md.sys.color.on-surface-variant` | `.ax-list__overline` | PASS |
| leading icon color | `md.sys.color.on-surface-variant` | `.ax-list__leading` | PASS |
| trailing icon color | `md.sys.color.on-surface-variant` | `.ax-list__trailing` | PASS for generic trailing icon row |
| unselected trailing icon color | `md.sys.color.on-surface` | direct trailing icon children use `on-surface`; generic trailing slot remains `on-surface-variant` | PASS after Phase 5 patch |
| trailing supporting text color | `md.sys.color.on-surface-variant` | `.ax-list__trailing-text` | PASS |
| leading Avatar color | `md.sys.color.primary-container` | composed `.ax-avatar`; owned by Avatar #32 | PASS by composition boundary |
| leading Avatar label color | `md.sys.color.on-primary-container` | composed `.ax-avatar`; owned by Avatar #32 | PASS by composition boundary |
| container elevation | `md.sys.elevation.level0` | no shadow; level0 comment | PASS |
| selected container color | `md.sys.color.secondary-container` | `.is-selected`, `[aria-selected=true]` | PASS |
| selected text/icon color | `md.sys.color.on-secondary-container` | selected child slot recolor | PASS |

Phase 3 found two small mismatch candidates:

- segmented container color: `transparent` vs M3 `surface`;
- unselected trailing icon split: M3 distinguishes `on-surface` for unselected
  trailing icon while baseline currently uses `on-surface-variant` for the
  trailing slot.

Phase 5 decision:

```txt
Option A: v3.5.11 in-cycle baseline patch        APPLIED
Option B: BACKLOG #33 List baseline alignment    NOT OPENED
```

The patch stayed inside `components.css` §26. Playwright also caught that
direct icon children needed selected/disabled overrides so the new unselected
icon color would not flatten state-specific colors.

---

## §8 — WCAG SC Accuracy

### SC 1.3.1 Info and Relationships, Level A

List structure, headings, labels, dividers, and selectable state must preserve
relationships. Static lists should use real list semantics where appropriate.
Interactive rows should not erase native role semantics with inappropriate
`role=listitem` overrides.

### SC 1.4.3 Contrast (Minimum), Level AA

Applies to:

- label text,
- supporting text,
- overline,
- trailing text,
- disabled text.

Phase 2 visual QA must check selected/unselected/disabled combinations.

### SC 1.4.11 Non-text Contrast, Level AA

Applies to:

- focus outline,
- selected background distinction,
- state-layer visibility,
- dividers,
- leading/trailing icons.

### SC 2.1.1 Keyboard, Level A

Applies to interactive rows. Native `button` and `a` rows satisfy basic
keyboard operation. Managed listbox/selection patterns require an owner for
arrow-key and selection behavior; v3.5.11 does not create that runtime.

### SC 2.4.3 Focus Order, Level A

Applies to action/navigation rows and any composed trailing controls. Phase 2
must avoid nested focus traps and ambiguous row/control order.

### SC 2.4.7 Focus Visible, Level AA

Baseline provides a 2px secondary outline with `outline-offset: -3px`. Phase 3
must verify it remains visible across segmented, selected, and disabled states.

### SC 2.5.8 Target Size (Minimum), Level AA

Interactive list rows meet SC 2.5.8:

- 56px / 72px / 88px item heights exceed the 24px minimum.

Trailing icon buttons, if added, must rely on their composed component target
contract.

### SC 2.5.5 Target Size (Enhanced), Level AAA

Interactive rows meet the 44px enhanced target by height:

- 56px minimum row height exceeds 44px.

Small nested trailing controls must be evaluated through their own component
contract.

### SC 4.1.2 Name, Role, Value, Level A

Applies strongly to:

- action row accessible names,
- navigation row roles,
- selected state representation,
- disabled state representation,
- selectable list patterns if introduced.

The current `button role=listitem` style-guide pattern is a Phase 1 risk for
SC 4.1.2 and must not be canonized.

### SC 4.1.3 Status Messages, Level AA

Not applicable to primitive static/action list rows. It becomes relevant only
if plugin/runtime behavior dynamically loads, filters, selects, or reorders
items and must announce changes.

---

## §9 — Playwright QA Plan

Phase 2/3 Playwright checks:

- row height:
  - 56px single-line,
  - 72px two-line,
  - 88px three-line;
- leading icon center alignment;
- Avatar leading slot alignment;
- image leading slot size;
- trailing icon/text alignment;
- selected row color/radius;
- disabled row state;
- focus-visible outline visibility;
- hover/active radius transition;
- segmented 2px gap;
- narrow viewport overflow;
- static rows have no ripple;
- interactive rows have bounded ripple only on item surface.

Viewport matrix:

- mobile: 390px,
- tablet: 768px,
- desktop: 1280px.

---

## §10 — Measurement Risks

| Risk | Disposition |
|---|---|
| Long label + trailing text overflow | Phase 2/3 narrow viewport check |
| Avatar/image leading slot compresses text | Include stress specimen |
| Focus outline clipped by dense row | Verify `outline-offset: -3px` |
| Selected state color-only indication | Pair selected context with semantics/text |
| Trailing action target too small | Use composed Icon button/Button contract |
| Style-guide role override harms SC 4.1.2 | SPEC canonical semantics overrides current demo |

---

## §11 — Verdict

Phase 5 measurement verdict:

```txt
Baseline dimensions      PASS
M3 item heights          PASS
Slot geometry            PASS
WCAG SC specificity      PASS
Playwright QA            PASS
Token-level color map    PASS after v3.5.11 §26 patch
Phase 2/3 live proof     PASS
```

Playwright verified 56 / 72 / 88 row heights, 2px segmented gap, non-wrapping
trailing time text, 24px leading/trailing icons, 56px image slot, item-only
ripple hosts, 0 list-container ripple hosts, 0 static-row ripple hosts, and 0
mobile overflow across 390 / 768 / 1280 viewports.

---

## §12 — Cross-References

- `LIST-SPEC-AUDIT.md`
- `LIST-WP-MAPPING.md`
- `docs/v3.5.11/LIST-PHASE-0-REPORT.md`
- `components.css` §26
- `style-guide.html#components-list`
- Button group v3.5.10 measurement precedent
- Text field v3.5.7 WCAG specificity precedent

---

## §13 — v3.5.13 Token-Coverage Measurement Extension

This section supports BACKLOG #33. It extends measurement coverage without
reopening the v3.5.11 closure.

| M3 token area | Current evidence | Phase 2 need |
|---|---|---|
| Disabled | `components.css` §26 uses on-surface 10% background and 38% content | Verify selected-disabled row nuance |
| Hover | §0 Pattern A hover opacity 0.08 via state-layer foundation | Documentation-only unless §0 mismatch found |
| Focus | §0 focus opacity 0.10 + List outline `2px solid secondary`, offset -3px | Compare against M3 3dp focus indicator |
| Pressed | §0 pressed opacity 0.10 | Documentation-only unless local List override needed |
| Dragged | §0 supports `[data-dragging]` opacity, but List runtime is deferred | No runtime patch in v3.5.13 |
| Spacing | §26 has 16px leading/trailing, 10px top/bottom, 12px between, 2px segment gap | Playwright confirm |
| Shape | §26 has rest extra-small, hover medium, focus/pressed/selected large | Row-by-row compare with expressive shape table |
| Size | §26 has 56/72/88 rows, 24 icon, 56 image | Video slot remains deferred |
| Typography | body-large, body-medium, label-small tokens used | Confirm against dump row labels |

Candidate Phase 2 patch list:

```txt
1. Focus indicator thickness if measurement confirms M3 3dp should override
   the current 2px generic focus outline.
2. Selected-disabled color/opacity if current Pattern A cascade fails the M3
   selected-disabled row.
```

Phase 2/3 measurement result:

| Area | Verified result |
|---|---|
| Focus indicator | 3px thickness / -3px offset |
| Selected-disabled | Container resolves to 38% on-surface mix |
| Segmented gap | 2px gap remains; wrapper resolves transparent / radius 0 / padding 0 while item containers remain surface |
| Expand trailing icon container | 40px container, `corner-full`, surface-container color (`#211f26` in dark scheme) |
| Trailing supporting time | `white-space: nowrap`; no minute split in long-content stress specimen |

The segmented wrapper does not own a color token. The M3 token row is interpreted
as the segmented item container color, so item containers resolve to
`md.sys.color.surface` and the 2px gap reveals the surrounding surface.

Explicit non-patches for v3.5.13:

```txt
- Drag/reorder runtime.
- Video slot implementation.
- Generic §0 state-layer rewrite.
- Avatar primitive changes.
```

BACKLOG #33 is closed in v3.5.13 Phase 5.
