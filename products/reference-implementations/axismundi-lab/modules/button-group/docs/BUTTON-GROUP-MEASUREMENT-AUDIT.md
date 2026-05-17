# Button Group — Measurement Audit (v3.5.10 Phase 5 close)

> **Component**: Button group #6  
> **Status**: Phase 5 close — v3.5.10 DONE. Phase 3 Playwright measurements recorded.  
> **Companions**: `./BUTTON-GROUP-SPEC-AUDIT.md`, `./BUTTON-GROUP-WP-MAPPING.md`

---

## §0 — Scope

This audit covers Button group dimensions, geometry, spacing, token usage, and
WCAG applicability.

It does not change the baseline.

---

## §1 — M3 Measurement Digest

Official M3 Button group pages, extracted via Playwright in Phase 0, state:

```txt
Variants:
  standard
  connected

Sizes:
  XS / S / M / L / XL

Selection:
  single-select
  multi-select
  selection-required

Accessibility:
  each button in a group should have a minimum 48x48dp target
```

M3 token extraction observed:

```txt
Button group xsmall container height: 32dp
Button group xsmall between space:    18dp
```

The full token table is JS-rendered and should be re-checked during Phase 2
Playwright QA if exact size rows are required.

---

## §2 — Axismundi Current Dimensions

Current baseline:

```txt
Button group segments consume .ax-button.
Default .ax-button height = --comp-button-height = 40px.
Button group local pill radius = calc(var(--comp-button-height) / 2) = 20px.
```

Connected group spacing:

```txt
gap = 2px
```

Standard group spacing:

```txt
default gap = var(--space-sm) = 8px
XS gap = 18px
S gap = 12px
```

Connected size classes currently affect corner values and min widths, not the
overall button height.

---

## §3 — Geometry Table

| Surface | Current value | Source | Verdict |
|---|---:|---|---|
| Default segment height | 40px | `--comp-button-height` | PASS for current baseline |
| Connected gap | 2px | §28.7 | PASS |
| Standard default gap | 8px | `--space-sm` | PASS |
| Standard XS gap | 18px | `.is-size-xs` | Needs M3 exact check |
| Standard S gap | 12px | `.is-size-s` | Needs M3 exact check |
| Connected M inner rest | 8px | corner-small | PASS |
| Connected M pressed | 4px | corner-extra-small | PASS |
| Connected selected rest | 20px | v3.5.9 finite pill | PASS |
| Connected outer first/last | 20px | v3.5.9 finite pill | PASS |

---

## §4 — Size Variant Analysis

Known M3 sizes:

```txt
XS / S / M / L / XL
```

Known Axismundi implementation:

```txt
.ax-button-group.is-size-xs
.ax-button-group.is-size-s
.ax-button-group--connected.is-size-xs
.ax-button-group--connected.is-size-l
.ax-button-group--connected.is-size-xl
```

Open Phase 2 verification:

```txt
Does the current baseline need actual height variants for Button group,
or should Button group keep inheriting Button's 40px baseline until a larger
Button family size system lands?
```

Phase 1 recommendation:

```txt
Audit all size classes.
Phase 2 should demonstrate default M plus representative XS/L/XL geometry,
but must verify whether those classes actually change rendered dimensions.
Do not add public size tokens until Phase 2 visual evidence confirms the need.
```

Phase 3 finding:

```txt
Playwright measured XS / L / XL specimens at the same 40px segment height as
default M. The current size hooks affect gap, min-inline-size, and connected
corner geometry only; they do not implement full M3 Button group sizing.

M3 Button groups adapt to the height/density of the buttons inside. Axismundi
Button #1 currently exposes only the 40px baseline button size; XS/M/L/XL
Button size variants remain deferred. Therefore Button group cannot honestly
claim full size coverage in v3.5.10.
```

---

## §5 — WCAG SC Accuracy

### SC 2.5.8 Target Size (Minimum), Level AA

Requirement:

```txt
24x24 CSS px minimum, with exceptions.
```

Button group verdict:

```txt
PASS expected.
Segments inherit button surfaces and current connected XS/S enforce
min-inline-size: 48px.
```

### SC 2.5.5 Target Size (Enhanced), Level AAA

Requirement:

```txt
44x44 CSS px target.
```

Button group nuance:

```txt
M3 accessibility guidance says each button in a group should have 48x48dp.
Current default segment height is 40px, but touch-target adequacy may be
achieved by min width / padding / Button family conventions.
```

Phase 2 must verify actual rendered hit target. Do not claim AAA PASS until
Playwright measures at least 44x44 target boxes for every live specimen.

### SC 2.1.1 Keyboard, Level A

Pattern A:

```txt
Native radio keyboard behavior. Tab enters group; arrows move selection.
```

Phase 3 Playwright observation:

```txt
ArrowLeft / ArrowRight changed checked state in Pattern A.
Home / End did not change checked state in Chrome native radio flow.
This remains acceptable for v3.5.10 because Pattern A is native/no-JS and
does not polyfill browser radio behavior.
```

Pattern B:

```txt
Tab through buttons; Space/Enter activates.
```

Both are valid when matched to the right semantics.

### SC 4.1.2 Name, Role, Value, Level A

Pattern A:

```txt
input[type=radio] exposes role/state/value.
legend provides group label.
label text provides accessible name.
```

Pattern B:

```txt
button exposes role.
aria-pressed exposes value.
visible label or aria-label provides name.
```

### SC 1.4.3 Contrast (Minimum), Level AA

Phase 2 must verify:

```txt
selected text vs secondary-container
unselected text vs surface/transparent background
disabled text at 38% on-surface treatment
```

### SC 1.4.11 Non-text Contrast, Level AA

Phase 2 must verify:

```txt
focus outline
connected segment boundaries
selected indicator / container color
```

### SC 3.2.2 On Input, Level A

Radio selection should not unexpectedly submit, navigate, or trigger irreversible
action. Plugin / integrator behavior must keep value selection separate from
submission.

---

## §6 — Token Coverage

Current token-driven surfaces:

| Surface | Token | Verdict |
|---|---|---|
| default height | `--comp-button-height` | token-driven |
| selected pill | `--_button-group-pill-radius` | local token-driven |
| connected gap | literal `2px` | M3 literal; acceptable |
| standard gap | `--space-sm` / size-specific literals | mixed |
| inner rest corners | `--md-sys-shape-*` | token-driven |
| inner pressed corners | `--md-sys-shape-*` | token-driven |
| selected fill | `--md-sys-color-secondary-container` | token-driven |
| selected text | `--md-sys-color-on-secondary-container` | token-driven |
| focus outline | `--md-sys-color-secondary` | token-driven |

Potential Phase 2 decision:

```txt
Keep local --_button-group-pill-radius
or promote public --comp-button-group-* tokens.
```

MEASUREMENT recommendation:

```txt
Do not promote tokens until Phase 2 confirms actual size-variant needs.
```

---

## §7 — Playwright QA Plan

Phase 2/3 should measure:

```txt
1. default segment height and width
2. connected gap = 2px
3. selected segment rest radius = 20px
4. selected+pressed radius = 4px inner / 20px outer when first/last
5. focus outline geometry
6. icon-only segment target size
7. radio keyboard behavior
8. aria-pressed toggle behavior in demo JS, if demo JS exists
9. mobile overflow for 5-segment group
10. ripple bounded geometry per segment
```

Recommended viewport matrix:

```txt
390px mobile
768px tablet
1280px desktop
```

---

## §8 — Measurement Verdict

```txt
Phase 1 PASS for measurement audit completeness.

Known current status:
  - M default geometry is documented.
  - v3.5.9 pill morph correction is verified.
  - WCAG SC list is explicit.
  - Exact size-variant/full-target PASS waits for Phase 2 Playwright.
```

Phase 2 Playwright observation:

```txt
Default M connected segments render at 40px height in the lab pattern page.
Icon-only specimens render at approximately 52px x 40px.

SC 2.5.8 AA remains PASS (40 >= 24).
SC 2.5.5 AAA remains NOT PASS for default M height (40 < 44), matching the
Button #1 target-size precedent. Do not claim AAA PASS for Button group until
the baseline grows a larger hit target or a future token cycle introduces a
48px target wrapper.
```

---

## §9 — Phase 5 Close Findings (v3.5.10)

### Size variants partial — BACKLOG #32

Phase 3 Playwright confirmed M3 XS/S/M/L/XL size variants are NOT actually applied
in v3.5.10:

```txt
XS height:  40px  (expected smaller per M3 spec)
M height:   40px  (default)
L height:   40px  (expected larger per M3 spec)
XL height:  40px  (expected largest per M3 spec)
font-size:  14px  (constant across sizes)
```

Root cause:

```txt
baseline §28 is-size-xs/s/l/xl hooks only adjust:
  - gap / min-inline-size (XS / S)
  - connected inner corner radius (L / XL)
They do NOT change segment height, font-size, or padding because those
properties cascade from .ax-button at default 40px / 14px / 16px.
```

Button group inherits Button #1's single-size baseline (default 40px only).

Resolution: tracked as BACKLOG #32 — Button family size variants cycle. Full M3
XS/S/M/L/XL coverage requires a coordinated cycle across Button #1, Icon button
#2, and Button group #6.

v3.5.10 does NOT claim full M3 size coverage. The pattern HTML §6 specimen is
labelled "Size hooks — partial baseline" with a visible warning.

### SC 2.5.5 AAA — honest NOT PASS

Phase 3 Playwright confirmed default M segments at 40px height. SC 2.5.5 AAA
(44×44) remains NOT PASS, matching the Button #1 target-size precedent. AA SC
2.5.8 (24×24) is PASS.

### Pattern A Home/End — native browser behavior

Phase 3 Playwright observed ArrowLeft/ArrowRight changes checked state in
Pattern A, but Home/End does not. This is native Chrome radio behavior and is
acceptable because Pattern A is locked to native/no-JS — Axismundi does NOT
polyfill browser radio semantics.

### Mobile overflow

```txt
390 / 768 / 1280 viewport overflowX: 0
```

No mobile overflow finding in v3.5.10.

---

## §10 — Phase 5 Verdict

```txt
v3.5.10 close: PASS with honest size-partial finding.

PASS items:
  - Geometry contract verified at default M.
  - v3.5.9 pill morph correction inherited and verified.
  - WCAG SC list explicit.
  - Mobile overflow none.
  - Pattern A radio keyboard verified.

Open items (deferred):
  - Full XS/S/M/L/XL size coverage → BACKLOG #32.
  - SC 2.5.5 AAA → honest NOT PASS for default M; revisit on Button family
    size cycle.
```

---

## §11 — v3.5.13 Size-Matrix Measurement Addendum

Current measured baseline:

```txt
Default segment height: 40px
XS/S/L/XL hooks:       present but not full size variants
SC 2.5.8 AA:           PASS
SC 2.5.5 AAA:          honest NOT PASS for default M
```

Required v3.5.13 Phase 2/3 measurement matrix:

```txt
XS / S / M / L / XL:
  segment height
  label font size
  icon size
  connected outer radius
  connected inner radius
  selected + pressed morph
  Pattern A radio keyboard smoke
  Pattern B aria-pressed keyboard smoke
```

Acceptance condition:

```txt
Each size must preserve v3.5.9 finite pill stability. Outer connected corners
must remain pill-stable while inner corners follow size-specific M3 geometry.
```
