# Button Family Size Audit

> **Status**: v3.5.13 Phase 5 closed.  
> **Lane**: A — BACKLOG #32 Button family size variants.  
> **Scope**: Button #1, Icon button #2, Button group #6.  
> **Verdict**: PASS — Option C implemented and verified.

---

## §0 — Status / Scope

This audit resolves the Phase 1 token-surface decision required before any
baseline patch for BACKLOG #32.

Affected closed components:

```txt
Button #1          v3.5.1
Icon button #2     v3.5.2
Button group #6    v3.5.10
```

This is a cross-cutting cleanup audit, not a component release reopen.

---

## §1 — Inputs Read

```txt
BACKLOG.md #32
docs/v3.5.13/WAVE-1-CLEANUP-PHASE-0-REPORT.md
tokens.css §9 Component tokens
components.css §2 Button
components.css §3 Icon button
components.css §28 Button group
BUTTON-SPEC-AUDIT.md
BUTTON-MEASUREMENT-AUDIT.md
ICON-BUTTON-SPEC-AUDIT.md
ICON-BUTTON-MEASUREMENT-AUDIT.md
BUTTON-GROUP-SPEC-AUDIT.md
BUTTON-GROUP-MEASUREMENT-AUDIT.md
```

Official M3 pages require JavaScript for text extraction. Phase 2 should use
Playwright when exact current size tables need browser-side confirmation.

---

## §2 — Current Baseline Inventory

Current shared token state:

```txt
--comp-button-height: 40px
--comp-button-radius: calc(var(--comp-button-height) / 2)
--comp-icon-size-sm: 20px
--comp-icon-size-md: 24px
--comp-icon-size-lg: 28px
--comp-icon-size-xl: 32px
--comp-touch-target: 48px
```

Current component behavior:

```txt
Button:
  height 40px, label-large typography, 16px horizontal padding, 20px icon.

Icon button:
  visual container 40px, touch target 48px, 24px glyph, no is-size-* hooks.

Button group:
  inherits Button height/typography/padding.
  is-size-* hooks adjust gap and connected corner radius only.
```

The v3.5.10 finding is therefore expected: Button group size hooks cannot
change rendered segment height until Button family size tokens exist.

---

## §3 — M3 Size Matrix Need

The family needs an explicit XS / S / M / L / XL size contract for:

```txt
container height
horizontal padding
icon size
label typography
touch target
connected group corner geometry
```

Current baseline only implements default M/S-like 40px behavior. The naming in
comments says "S size" for Button while BACKLOG #32 calls it default M. Phase 2
should normalize public docs without changing old release history.

---

## §4 — Token Surface Options

Option A — public per-size tokens for every dimension:

```txt
Pros: explicit, stable.
Cons: many tokens at once; risk of token graph bloat.
```

Option B — local variables only:

```txt
Pros: minimal public token commitment.
Cons: repeats size logic across Button, Icon button, and Button group.
```

Option C — hybrid:

```txt
Public core size tokens:
  height
  icon size
  padding
  typography hook where needed

Local mappings:
  per-component selector application
  Button group connected inner/outer radius
```

Decision:

```txt
Option C.
```

---

## §5 — Recommended Token Contract

Phase 2 candidate public tokens:

```txt
--comp-button-height-xs
--comp-button-height-s
--comp-button-height-m
--comp-button-height-l
--comp-button-height-xl

--comp-button-padding-inline-xs
--comp-button-padding-inline-s
--comp-button-padding-inline-m
--comp-button-padding-inline-l
--comp-button-padding-inline-xl

--comp-button-icon-size-xs
--comp-button-icon-size-s
--comp-button-icon-size-m
--comp-button-icon-size-l
--comp-button-icon-size-xl
```

Compatibility alias:

```txt
--comp-button-height remains as the default M/S-compatible legacy alias.
--comp-button-radius remains calc(var(--comp-button-height) / 2) unless Phase 2
proves a safer alias strategy.
```

Typography:

```txt
Do not create typography-size tokens until Phase 2 Playwright extraction
confirms the exact M3 table. If needed, use local component mappings to
existing typescale tokens rather than inventing new font-size literals.
```

---

## §6 — Button Mapping

Button should gain:

```txt
.ax-button.is-size-xs
.ax-button.is-size-s
.ax-button.is-size-m
.ax-button.is-size-l
.ax-button.is-size-xl
```

Expected mapping:

```txt
height -> --comp-button-height-*
padding-inline -> --comp-button-padding-inline-*
icon -> --comp-button-icon-size-*
radius -> calc(current height / 2)
active radius -> existing M3 pressed corner rules, confirmed per size
```

Phase 2 must preserve v3.5.9 finite-pill interpolation safety.

---

## §7 — Icon Button Mapping

Icon button should gain:

```txt
.ax-icon-button.is-size-xs
.ax-icon-button.is-size-s
.ax-icon-button.is-size-m
.ax-icon-button.is-size-l
.ax-icon-button.is-size-xl
```

Expected mapping:

```txt
visual container -> size-specific height token or icon-button local alias
glyph -> --comp-button-icon-size-* or icon-button-specific local mapping
touch target -> not below --comp-touch-target unless M3 explicitly permits and
                WCAG analysis documents the result
```

SC 2.5.5 AAA must be re-evaluated per size. Smaller visual containers may still
have 48px hit area through min-width/min-height.

---

## §8 — Button Group Mapping

Button group remains a consumer of Button size tokens.

Expected mapping:

```txt
.ax-button-group.is-size-* .ax-button
  inherits or maps to matching .ax-button.is-size-* values.

Connected geometry:
  local variables continue to own outer/inner radius and selected+pressed
  morph because connected groups have geometry not shared by standalone Button.
```

Do not promote Button group connected geometry to public tokens in v3.5.13
unless Phase 2 proves repeated use outside Button group.

---

## §9 — WCAG Target-Size Implications

Relevant SCs:

```txt
SC 2.5.8 Target Size (Minimum), Level AA
SC 2.5.5 Target Size (Enhanced), Level AAA
```

Current:

```txt
Button default 40px and Button group default 40px:
  SC 2.5.8 AA PASS
  SC 2.5.5 AAA honest NOT PASS

Icon button visual 40px with 48px min target:
  SC 2.5.8 AA PASS
  SC 2.5.5 AAA PASS for target box if 48px hit area remains active.
```

Phase 2/3 must measure both visual box and effective hit target.

---

## §10 — Phase 2 Patch Surface

Expected:

```txt
tokens.css §9
components.css §2 Button
components.css §3 Icon button
components.css §28 Button group
```

Not expected:

```txt
style-guide.html
blocks.css
theme.json
lab module JS
```

Phase 2 must be plan-first and must include exact before/after selectors.

---

## §11 — Playwright QA Matrix

Required after Phase 2:

```txt
Button:
  XS/S/M/L/XL height
  padding
  icon size
  active morph radius

Icon button:
  visual box
  hit target
  glyph size
  selected state geometry

Button group:
  segment height
  connected outer/inner radius
  selected+pressed morph
  Pattern A radio smoke
  Pattern B aria-pressed smoke
```

Viewports:

```txt
390px
768px
1280px
```

---

## §12 — Risks / Fallback Triggers

Fallback to future release if:

```txt
- exact M3 tables require broader typography-system changes,
- Button and Icon button need incompatible naming surfaces,
- style-guide.html markup must be rewritten,
- touch-target behavior becomes ambiguous,
- v3.5.9 pill-stable contract regresses.
```

---

## §13 — Verdict

```txt
Phase 1 size audit: PASS
Token-surface decision: Option C
Phase 2 plan-first: REQUIRED
Baseline edit in Phase 1: none
```

BACKLOG #32 remains open until Phase 2/3 implement and verify the size matrix.

---

## §14 — Non-Goals

This audit does not:

- edit `tokens.css`,
- edit `components.css`,
- edit `style-guide.html`,
- close BACKLOG #32,
- implement size variants,
- change v3.5.9 pill-stable token values,
- add FAB size variants,
- create a new Button group runtime,
- reopen closed component verdicts.

---

## §15 — Phase 2 Playwright Extraction Update

Phase 2 pre-work used Playwright against the JavaScript-rendered M3 spec pages
for Buttons, Icon buttons, and Button groups.

Extracted Button size rows:

| Size | Container height | Icon size | Leading space | Between icon/label | Trailing space | Outline |
|---|---:|---:|---:|---:|---:|---:|
| XS | 32dp | 20dp | 12dp | 8dp | 12dp | 1dp |
| S | 40dp | 20dp | 16dp | 8dp | 16dp | 1dp |
| M | 56dp | 24dp | 24dp | 8dp | 24dp | 1dp |
| L | 96dp | 32dp | 48dp | 12dp | 48dp | 2dp |
| XL | 136dp | 40dp | 64dp | 16dp | 64dp | 3dp |

Extracted Icon button size rows:

| Size | Container height | Icon size | Narrow space | Default space | Wide space | Outline |
|---|---:|---:|---:|---:|---:|---:|
| XS | 32dp | 20dp | 4dp | 6dp | 10dp | 1dp |
| S | 40dp | 24dp | 4dp | 8dp | 14dp | 1dp |
| M | 56dp | 24dp | 12dp | 16dp | 24dp | 1dp |
| L | 96dp | 32dp | 16dp | 32dp | 48dp | 2dp |
| XL | 136dp | 40dp | 32dp | 48dp | 72dp | 3dp |

Extracted Button group rows:

| Group token | Value |
|---|---:|
| standard XS container height | 32dp |
| standard XS between space | 18dp |
| standard inner padding XS / S / M / L / XL | 18dp / 12dp / 8dp / 8dp / 8dp |
| connected inner padding | 2dp all sizes |
| connected inner corner XS / S / M / L / XL | 4dp / 8dp / 8dp / 16dp / 20dp |
| XS/S connected target/minimum width | 48dp |

Shared corner extraction:

| Size | Square/rest corner | Pressed corner |
|---|---:|---:|
| XS | 12dp | 8dp |
| S | 12dp | 8dp |
| M | 16dp | 12dp |
| L | 28dp | 16dp |
| XL | 28dp | 16dp |

Label-size caveat:

```txt
The M3 page exposes label size as an `Aa` specimen rather than numeric text in
the extracted DOM. v3.5.13 Phase 2 should keep existing typescale tokens for
label text unless a later extraction obtains numeric label rows. Do not invent
literal font-size values.
```

Phase 2 may now proceed with Option C using the extracted height, padding, icon,
outline, and corner tables, while leaving typography mapped to existing
typescale tokens.

---

## §16 — Phase 2 Execution Result

Phase 2 implemented the size matrix as a narrow baseline patch:

- `tokens.css` adds Button family size tokens for height, inline padding, icon
  size, and outline width.
- `components.css` §2 maps `.ax-button.is-size-xs/s/m/l/xl` to the extracted
  Button matrix.
- `components.css` §3 maps `.ax-icon-button.is-size-xs/s/m/l/xl` to the
  extracted Icon button matrix.
- `components.css` §28 maps `.ax-button-group.is-size-xs/s/m/l/xl` and
  `.ax-button-group--connected` geometry to the extracted Button group matrix.
- `components.css` §26 applies the List token-extension patch for focus
  indicator thickness and selected-disabled container nuance.

Playwright verification after the patch:

| Surface | Verified result |
|---|---|
| Button | XS/S/M/L/XL heights = 32/40/56/96/136; inline padding = 12/16/24/48/64; icon box = 20/20/24/32/40 |
| Icon button | XS/S/M/L/XL visual box = 32/40/56/96/136; icon box = 20/24/24/32/40 |
| Button group | Standard spacing = 18/12/8/8/8; connected gap = 2; XS/S connected min width = 48 |
| List | Focus indicator = 3px / -3px; selected-disabled container resolves to 38% on-surface mix |

Default no-size Button behavior remains unchanged for Wave 1 regression safety:
size variants are opt-in through `is-size-*` hooks.

The label typography caveat remains unchanged. The M3 page exposed label size as
`Aa`, so v3.5.13 intentionally keeps the existing Axismundi typescale tokens
rather than inventing literal font-size values.

Phase 3 follow-up:

```txt
Card composition QA surfaced the same morph-safety problem on composed
standard Icon buttons that v3.5.9 had already fixed for Button. Phase 3
therefore replaced Icon button's resting `corner-full` dependency with a
finite radius derived from the effective icon-button box.

Result: default composed Icon button now resolves 48px box / 24px resting
radius and animates through finite values to its pressed corner. The former
9999px → 8px interpolation path is removed.
```
