# v3.5.13 Wave 1 Closure Cleanup — Phase 2 Plan

> **Status**: Phase 5 closed.  
> **Release**: v3.5.13.  
> **Input**: Phase 1 execution approved.  
> **Rule**: baseline edits allowed only after this plan is approved.  
> **Lanes**: C Records, B List token coverage, A Button family size variants.

---

## §0 — Phase 2 Goal

Phase 2 should convert Phase 1 audit findings into the minimum safe patch set.

Lane order remains:

```txt
1. Lane C — Records sweep
2. Lane B — List token coverage
3. Lane A — Button family size variants
```

Phase 2 expected patch posture:

```txt
Lane C: documentation-only, no CSS patch.
Lane B: likely narrow `components.css §26` patch only if confirmed.
Lane A: `tokens.css` + `components.css §2/§3/§28` patch after exact M3 size
        table extraction.
```

---

## §1 — Pre-Edit Requirements

Before editing any baseline file:

```txt
1. Run validator:
   python .\tools\validators\validate_theme_pilot.py

2. Capture baseline mtimes:
   tokens.css
   components.css
   style-guide.html
   blocks.css
   ontology-theme-pilot/theme.json

3. Use Playwright to extract the current M3 size token tables:
   - Buttons specs
   - Icon buttons specs
   - Button groups specs
```

Important:

```txt
The official M3 pages are JavaScript-rendered. Normal text fetch returns only
"This website requires JavaScript." Use Playwright extraction and record the
values in the Phase 2 execution report.
```

Abort if:

```txt
Exact M3 values cannot be extracted with enough confidence for Lane A.
```

---

## §2 — Lane C Patch Decision

Decision:

```txt
No Phase 2 patch for Lane C.
```

Reason:

```txt
Records were completed in Phase 1:
  AVATAR-RECORD-AUDIT.md
  DIVIDER-RECORD-AUDIT.md
  BADGE-RECORD-AUDIT.md

Their purpose is to document Baseline-only Record status. They intentionally
do not create lab CSS/JS/pattern artifacts and do not change matrix status from
RECORD.
```

Phase 2 action:

```txt
None, except preserve these files and include them in Phase 3/5 checks.
```

---

## §3 — Lane B Patch Decision: List Token Coverage

Phase 1 identified two candidate narrow checks:

```txt
1. Focus indicator:
   Current baseline: 2px solid secondary, offset -3px.
   M3 token dump:    3dp focus indicator thickness, -3dp inner offset.

2. Selected-disabled nuance:
   Current baseline: generic disabled Pattern A and selected state cascade.
   M3 token dump:    explicit selected-disabled container/content opacity rows.
```

Decision:

```txt
Lane B may patch `components.css §26` only if Playwright/computed-style
verification confirms the mismatch is real and local.
```

Planned §26 patch candidates:

```css
/* Candidate B1: focus indicator thickness */
.ax-list__item:focus-visible {
  outline: 3px solid var(--md-sys-color-secondary);
  outline-offset: -3px;
}
```

Candidate B2 should be more conservative:

```txt
Only add selected-disabled selectors if current cascade fails to produce the
M3 selected-disabled appearance. Do not preemptively add them.
```

Allowed file:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
  §26 List only
```

Fallback:

```txt
If candidate patch requires §0 state-layer changes, drag/reorder runtime,
Avatar changes, or non-§26 selectors, do not patch. Keep #33 as audit-extension
only and route broader work to future backlog.
```

---

## §4 — Lane A Token Matrix: Confirmed vs Extraction Required

Confirmed from M3 Button group Playwright extraction:

```txt
Button group standard - Size - Xsmall:
  container height: 32dp
  between space:    18dp

Standard group inner padding:
  XS: 18dp
  S:  12dp
  M:  8dp
  L:  8dp
  XL: 8dp

Connected group inner padding:
  all sizes: 2dp

Connected round inner corner sizes:
  XS: 4dp
  S:  8dp
  M:  8dp
  L:  16dp
  XL: 20dp

Connected square outer corner sizes:
  XS: 4dp
  S:  8dp
  M:  8dp
  L:  16dp
  XL: 20dp

Extra small and small connected button groups:
  target areas and minimum width: 48dp
```

Confirmed from Buttons / Icon buttons Playwright extraction:

```txt
Pressed corner sizes:
  XS: 8dp
  S:  8dp
  M:  12dp
  L:  16dp
  XL: 16dp

Square/rest corner sizes:
  XS: 12dp
  S:  12dp
  M:  16dp
  L:  28dp
  XL: 28dp

Round:
  Full for all sizes.
```

Extracted in Phase 2 pre-work and recorded in
`BUTTON-FAMILY-SIZE-AUDIT.md §15`:

```txt
Button:
  XS/S/M/L/XL container height
  XS/S/M/L/XL leading/trailing padding
  XS/S/M/L/XL between icon-label space
  XS/S/M/L/XL icon size
  XS/S/M/L/XL outline width

Icon button:
  XS/S/M/L/XL visual container height
  XS/S/M/L/XL glyph size
  XS/S/M/L/XL narrow/default/wide spaces
  XS/S/M/L/XL outline width

Button group:
  standard XS container height
  standard inner padding XS/S/M/L/XL
  connected inner padding
  connected inner corners
  XS/S connected target/min-width

Shared:
  square/rest corner and pressed corner matrix.
```

Still not numeric in extracted DOM:

```txt
Button label size appears as `Aa`. Keep existing typescale tokens in Phase 2
unless a later extraction obtains numeric label rows. Do not invent literals.
```

---

## §5 — Lane A `tokens.css` Diff Scope

Allowed file:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
```

Insertion location:

```txt
§9 Component tokens, directly after the existing Button token block:
  --comp-button-height
  --comp-button-radius
```

Existing lines must remain:

```css
--comp-button-height: 40px;
--comp-button-radius: calc(var(--comp-button-height) / 2);
```

New token surface:

```css
/* Button family size matrix — v3.5.13 BACKLOG #32 */
--comp-button-height-xs: 32px;
--comp-button-height-s:  40px;
--comp-button-height-m:  56px;
--comp-button-height-l:  96px;
--comp-button-height-xl: 136px;

--comp-button-padding-inline-xs: 12px;
--comp-button-padding-inline-s:  16px;
--comp-button-padding-inline-m:  24px;
--comp-button-padding-inline-l:  48px;
--comp-button-padding-inline-xl: 64px;

--comp-button-icon-size-xs: 20px;
--comp-button-icon-size-s:  20px;
--comp-button-icon-size-m:  24px;
--comp-button-icon-size-l:  32px;
--comp-button-icon-size-xl: 40px;
```

Do not add typography tokens unless M3 extraction proves an exact mapping and
Phase 2 execution can map them to existing typescale tokens without inventing
literal font-size values.

Label typography remains mapped to the existing label-large typescale in
v3.5.13.

---

## §6 — Lane A `components.css` Diff Scope: Button §2

Allowed section:

```txt
components.css §2 Button
```

Planned selector additions:

```css
.ax-button.is-size-xs { ... }
.ax-button.is-size-s  { ... }
.ax-button.is-size-m  { ... }
.ax-button.is-size-l  { ... }
.ax-button.is-size-xl { ... }
```

Each size hook should set only:

```txt
--_button-height
--_button-padding-inline
--_button-icon-size
--_button-pressed-radius
```

Preferred implementation style:

```css
.ax-button {
  --_button-height: var(--comp-button-height);
  --_button-padding-inline: var(--space-md);
  --_button-icon-size: var(--comp-icon-size-sm);
  --_button-pressed-radius: var(--md-sys-shape-corner-small);

  height: var(--_button-height);
  padding-inline: var(--_button-padding-inline);
  border-radius: calc(var(--_button-height) / 2);
}

.ax-button:active {
  border-radius: var(--_button-pressed-radius);
}

.ax-button > .ax-button-icon {
  width: var(--_button-icon-size);
  height: var(--_button-icon-size);
}
```

Then size hooks map to extracted values.

Preserve:

```txt
- existing variants,
- disabled rules,
- v3.5.9 finite pill interpolation safety,
- text variant tighter padding behavior unless Phase 2 explicitly maps text
  button sizes.
```

---

## §7 — Lane A `components.css` Diff Scope: Icon Button §3

Allowed section:

```txt
components.css §3 Icon button
```

Planned selector additions:

```css
.ax-icon-button.is-size-xs { ... }
.ax-icon-button.is-size-s  { ... }
.ax-icon-button.is-size-m  { ... }
.ax-icon-button.is-size-l  { ... }
.ax-icon-button.is-size-xl { ... }
```

Preferred implementation style:

```css
.ax-icon-button {
  --_icon-button-size: var(--comp-button-height);
  --_icon-button-icon-size: var(--comp-icon-size-md);
  --_icon-button-pressed-radius: var(--md-sys-shape-corner-small);

  width:  var(--_icon-button-size);
  height: var(--_icon-button-size);
}

.ax-icon-button > svg,
.ax-icon-button > .ax-icon {
  width: var(--_icon-button-icon-size);
  height: var(--_icon-button-icon-size);
}
```

Touch target rule:

```txt
Never reduce effective min-width/min-height below --comp-touch-target unless
Phase 2 records an explicit WCAG decision. Default recommendation: preserve
48px min target for XS/S.
```

Pressed radius:

```txt
Use M3 extracted pressed radius per size:
  XS 8dp, S 8dp, M 12dp, L 16dp, XL 16dp.
```

---

## §8 — Lane A `components.css` Diff Scope: Button Group §28

Allowed section:

```txt
components.css §28 Button group
```

Planned changes:

```txt
1. Keep local --_button-group-pill-radius.
2. Add size-local variables on `.ax-button-group`.
3. Map `.ax-button-group.is-size-*` to Button family size tokens.
4. Preserve connected gap = 2px for all sizes.
5. Preserve standard group gap:
   XS 18px, S 12px, M/L/XL 8px.
```

Preferred implementation style:

```css
.ax-button-group {
  --_button-group-button-height: var(--comp-button-height);
  --_button-group-button-padding-inline: var(--space-md);
  --_button-group-button-icon-size: var(--comp-icon-size-sm);
  --_button-group-pressed-radius: var(--md-sys-shape-corner-small);
  --_button-group-pill-radius: calc(var(--_button-group-button-height) / 2);
}

.ax-button-group .ax-button {
  height: var(--_button-group-button-height);
  padding-inline: var(--_button-group-button-padding-inline);
}
```

Connected corner mapping:

```txt
Rest inner:
  XS 4dp, S 8dp, M 8dp, L 16dp, XL 20dp.

Pressed:
  use M3 pressed corner sizes where applicable:
  XS 8dp, S 8dp, M 12dp, L 16dp, XL 16dp
  BUT preserve existing connected rule where outer corners stay pill-stable.
```

Minimum width:

```txt
XS/S connected segments keep min-inline-size 48px.
```

---

## §9 — Lane B Playwright / Patch Checks

Before deciding a §26 patch:

```txt
1. Generate a List focused item specimen.
2. Read computed outline width and offset.
3. Compare current selected-disabled computed colors/opacities.
4. Confirm whether generic §0 state-layer rows already satisfy hover/focus/
   pressed.
```

Patch allowed:

```txt
components.css §26 only.
```

Patch candidates:

```txt
Candidate B1:
  focus outline 2px -> 3px, offset remains -3px.

Candidate B2:
  selected-disabled explicit selectors, only if current cascade fails.
```

Fallback:

```txt
If no narrow mismatch is confirmed, Lane B remains documentation-only and
BACKLOG #33 may close as audit-extension complete only if user approves.
```

---

## §10 — Edit Protocol

For every edit:

```txt
1. Use apply_patch.
2. Read back the edited region.
3. If readback mismatches, stop and report.
4. Do not use automatic fresh Write fallback.
```

Validator sequence:

```txt
pre-edit validator
post-tokens.css validator
post-components.css validator
final validator
```

If Lane B and Lane A both patch `components.css`, combine into one carefully
reviewed `components.css` patch only after both scoped edits are known.

---

## §11 — Wave 1 Regression Smoke

After Phase 2 patch:

```txt
Button #1:
  size matrix renders, active morph still stable.

Icon button #2:
  glyph centered, target size preserved.

FAB family:
  no regression; independent size system.

Button group #6:
  connected geometry still stable, ripple per segment unaffected.

Card #9:
  no dependency on Button family size tokens except composed actions.

Text field #16:
  composed trailing icon-button still fits.

Search bar #17:
  trailing icon-button still fits and native clear suppression remains.

List #33:
  row heights and List token patch stable.

Carousel #34:
  no dependency.
```

---

## §12 — Playwright Pre-Check / Phase 3 Entry Criteria

Phase 2 execution must produce:

```txt
Button family size matrix:
  5 sizes × 3 components = 15 primary cases.

List token matrix:
  disabled / selected-disabled / hover / focus / pressed checks.

Validator:
  1.000 / 1.000 / 1.000 / 1.000 PASS.

Overflow:
  390 / 768 / 1280 viewport smoke, where pattern pages exist.
```

Phase 3 may begin only if:

```txt
- validator final PASS,
- Playwright pre-check PASS or documented N/A for a lane,
- no baseline patch escaped allowed sections,
- Wave 1 smoke has no blocker.
```

---

## §13 — Fallback Triggers

Lane A fallback:

```txt
- exact M3 size values unavailable,
- token surface becomes broader than Button family,
- style-guide.html markup required,
- v3.5.9 pill-stable morph regresses,
- SC target-size becomes ambiguous without design decision.
```

Lane B fallback:

```txt
- required patch escapes §26,
- required patch changes §0 state-layer foundation,
- selected-disabled requires behavior/runtime,
- dragged rows require reorder runtime.
```

Lane C fallback:

```txt
No expected fallback. Records are already documentation-only.
```

---

## §14 — Non-Goals

```txt
- No style-guide.html edits.
- No blocks.css edits.
- No theme.json edits.
- No lab JS edits.
- No publish prep.
- No repo rename.
- No GitHub setup.
- No docs/releases reorganization.
- No Wave 1 verdict reopen.
- No FAB size variants.
- No Split button implementation.
```

---

## §15 — Phase 2 Verdict

Plan verdict:

```txt
EXECUTED.
```

Condition resolved:

```txt
Playwright extracted exact Button/Icon button height, padding/space, icon, and
corner rows. Label size remains non-numeric (`Aa`) and stays on existing
typescale tokens.
```

Recommended execution path:

```txt
1. Pre-edit validator + mtime snapshot.
2. Lane B computed-style check.
3. Patch tokens.css with the extracted Button family token matrix.
4. Patch components.css §2/§3/§28 and optional §26.
6. Run validator after each baseline file.
7. Run Playwright size/token pre-check.
8. Report Phase 2 result.
```

Execution result:

```txt
Phase 2 baseline patch completed within the approved scope:
  - tokens.css
  - components.css §2 / §3 / §26 / §28

Playwright post-patch checks confirmed:
  - Button XS/S/M/L/XL = 32/40/56/96/136
  - Icon button XS/S/M/L/XL = 32/40/56/96/136
  - Button group standard spacing = 18/12/8/8/8
  - Button group connected gap = 2px and XS/S min width = 48px
  - List focus indicator = 3px with -3px offset
  - List selected-disabled container = 38% on-surface mix

Validator remained 1.000 / 1.000 / 1.000 / 1.000 PASS.
```
