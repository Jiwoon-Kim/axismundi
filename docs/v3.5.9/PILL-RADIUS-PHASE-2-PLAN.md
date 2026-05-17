# v3.5.9 — Pill Radius Correction Phase 2 Plan

> **Status**: Plan-only. Awaiting approval before baseline CSS edits.  
> **Source**: `docs/v3.5.9/PILL-RADIUS-CORRECTION-AUDIT.md`.  
> **Scope**: `tokens.css` + `components.css` only.

---

## §0 — Summary

Phase 2 applies the approved v3.5.9 morphing-safe pill correction.

The patch changes only confirmed morph sources:

```txt
Button
Button group connected / selected / selected+pressed outer pill sources
```

It does not change static `corner-full` semantics.

---

## §1 — File Scope

Edit exactly:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/components.css
```

Do not edit:

```txt
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/ontology-theme-pilot/theme.json
docs/v3.5.9/*.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md
CURRENT-STATE.md
NEXT-SESSION.md
products/reference-implementations/axismundi-lab/modules/**
```

Phase 5 will handle bookkeeping later.

---

## §2 — `tokens.css` Diff Scope

### §2.1 Shape scale insertion

Current context:

```css
--md-sys-shape-corner-extra-extra-large:     48px;
--md-sys-shape-corner-full:                  9999px;
```

Patch:

```css
--md-sys-shape-corner-extra-extra-large:     48px;
--md-sys-shape-corner-full:                  9999px;
--md-sys-shape-corner-pill-stable:           50%;
```

Lock:

```txt
Do not edit the `corner-full` line.
Add only the new `pill-stable` declaration adjacent to shape tokens.
```

### §2.2 Button component token

Current:

```css
--comp-button-height: 40px;
--comp-button-radius: var(--md-sys-shape-corner-full);
```

Patch:

```css
--comp-button-height: 40px;
--comp-button-radius: calc(var(--comp-button-height) / 2);
```

Rationale:

```txt
Button rest radius becomes 20px for a 40px button. Active radius remains 8px.
```

### §2.3 Button group tokens

Do not add global `--comp-button-group-*` tokens in Phase 2.

Reason:

```txt
Button group has not completed its v3.5.x Full-Spec audit yet. Local §28
custom properties are safer because they correct the baseline bug without
prematurely freezing the eventual public token contract for Button group #6.
```

Button group finite radii will be local to `components.css §28`.

---

## §3 — `components.css` Diff Scope — Button

No selector edits for Button.

Current:

```css
.ax-button {
  border-radius: var(--comp-button-radius); /* corner-full */
}
.ax-button:active {
  border-radius: var(--md-sys-shape-corner-small);
}
```

After token patch:

```txt
.ax-button rest radius resolves to 20px through --comp-button-radius.
.ax-button:active remains 8px.
```

Only update the nearby comment:

```txt
Before: Default shape corner-full (round)
After:  Default shape morphing-safe pill (height / 2)
```

Do not edit `.ax-button:active`.

---

## §4 — `components.css` Diff Scope — Button Group

### §4.1 Local variable block

Current:

```css
.ax-button-group {
  ...
}
```

Patch inside `.ax-button-group`:

```css
  --_button-group-pill-radius: calc(var(--comp-button-height) / 2);
```

Patch size variants:

```css
.ax-button-group.is-size-xs {
  --_button-group-pill-radius: 16px;
  gap: 18px;
}
.ax-button-group.is-size-s {
  --_button-group-pill-radius: 20px;
  gap: 12px;
}
.ax-button-group.is-size-l {
  --_button-group-pill-radius: 24px;
}
.ax-button-group.is-size-xl {
  --_button-group-pill-radius: 28px;
}
```

Rationale:

```txt
Default/M button group inherits the Button 40px height contract -> 20px.
XS/S are explicitly 48px min touch targets in connected mode but visual
button heights are not separately declared in baseline; use M3 size-derived
finite sources recorded in Phase 1 audit.
```

If Phase 2 inspection finds an existing compact XS visual height conflict,
abort and report instead of guessing.

### §4.2 Connected first/last outer corners

Replace all `corner-full` in these selectors with
`var(--_button-group-pill-radius)`:

```css
.ax-button-group--connected label.ax-button:first-of-type,
.ax-button-group--connected button.ax-button:first-of-type

.ax-button-group--connected label.ax-button:last-of-type,
.ax-button-group--connected button.ax-button:last-of-type
```

Same replacement for the `:active` restoration blocks:

```css
.ax-button-group--connected label.ax-button:first-of-type:active,
.ax-button-group--connected button.ax-button:first-of-type:active

.ax-button-group--connected label.ax-button:last-of-type:active,
.ax-button-group--connected button.ax-button:last-of-type:active
```

### §4.3 Connected selected segment

Replace:

```css
.ax-button-group--connected .ax-button-group__input:checked + .ax-button,
.ax-button-group--connected .ax-button[aria-pressed="true"],
.ax-button-group--connected .ax-button.is-selected {
  border-radius: var(--md-sys-shape-corner-full);
}
```

With:

```css
border-radius: var(--_button-group-pill-radius);
```

### §4.4 Selected+pressed outer restoration

Replace `corner-full` with `var(--_button-group-pill-radius)` in:

```css
.ax-button-group--connected label.ax-button:first-of-type.is-selected:active,
.ax-button-group--connected button.ax-button:first-of-type[aria-pressed="true"]:active,
.ax-button-group--connected .ax-button-group__input:checked + label.ax-button:first-of-type:active

.ax-button-group--connected label.ax-button:last-of-type.is-selected:active,
.ax-button-group--connected button.ax-button:last-of-type[aria-pressed="true"]:active,
.ax-button-group--connected .ax-button-group__input:checked + label.ax-button:last-of-type:active
```

Do not edit inner pressed values:

```css
corner-extra-small
corner-medium
corner-large
```

---

## §5 — Edit Protocol

Use `apply_patch` for the CSS edits.

Readback after each file edit:

```powershell
Get-Content products\reference-implementations\axismundi-lab\stylesheets\tokens.css |
  Select-Object -Skip 642 -First 25

Get-Content products\reference-implementations\axismundi-lab\stylesheets\tokens.css |
  Select-Object -Skip 810 -First 14

Get-Content products\reference-implementations\axismundi-lab\stylesheets\components.css |
  Select-Object -Skip 130 -First 45

Get-Content products\reference-implementations\axismundi-lab\stylesheets\components.css |
  Select-Object -Skip 4598 -First 245
```

Abort condition:

```txt
If readback is truncated, duplicated, or inconsistent with the patch, stop
and report before any further edit. Do not cascade fresh rewrites.
```

Fallback:

```txt
Only if user explicitly approves after mismatch: isolate the corrupted file
and restore from known-good content. Do not automatically mv+fresh-write.
```

---

## §6 — Validator Sequence

Run validator three times:

```txt
1. Pre-edit baseline confirmation.
2. Immediately after CSS edits.
3. After Playwright before/after capture and regression pre-check.
```

Command:

```powershell
python .\tools\validators\validate_theme_pilot.py
```

Expected:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

---

## §7 — Playwright Capture Plan

Phase 2 should capture before and after values.

Before edit:

```txt
Button rest radius should show 9999px or equivalent huge computed value.
Button active final radius should show 8px.
Intermediate frames may show large finite values.
```

After edit:

```txt
Button rest radius should show 20px.
Button active final radius should show 8px.
Intermediate frames should remain between 20px and 8px.
```

Button group:

```txt
Connected selected segment rest radius should be finite.
Selected+pressed inner radius should remain M3 pressed value.
First/last outer edge restoration should use finite pill radius.
```

Artifacts:

```txt
Do not commit screenshots or temporary Playwright scripts.
Report textual computed values only.
```

---

## §8 — Wave 1 Regression Pre-Check

After edit:

```txt
Button family:
  filled / tonal / elevated / outlined / text rest pill remains visually pill.
  :active morph no longer flickers.

Icon button:
  circular shape unchanged.

FAB:
  computed radius unchanged from finite 16/20/28px family.

Text field:
  no shape/token changes.

Search bar:
  static field pill unchanged.

Button group baseline:
  connected first/last outer edges remain pill-like.
  selected segment remains pill-like.
  selected+pressed inner corners still shrink.
```

---

## §9 — Rollback Plan

If Phase 2 patch causes regression:

```txt
1. Stop before Phase 5.
2. Capture failing selector/value.
3. Use apply_patch to revert only the touched declarations.
4. Re-run validator.
5. Report the failed strategy and return to Phase 1 audit for revision.
```

Do not use destructive repo-wide commands.

---

## §10 — Non-Goals

Do not:

```txt
- Change --md-sys-shape-corner-full.
- Edit style-guide.html.
- Edit theme.json.
- Edit Split button.
- Edit FAB.
- Edit Search bar/Text field/Ripple artifacts.
- Add Button group Full-Spec docs or pattern pages.
- Add JavaScript.
- Change transition durations or curves.
- Update CHANGELOG/ROADMAP/BACKLOG/CURRENT-STATE/NEXT-SESSION in Phase 2.
```

---

## §11 — Phase 3 Entry Criteria

Phase 3 can start only when:

```txt
1. tokens.css readback matches expected declarations.
2. components.css readback matches expected selector replacements.
3. Validator remains 1.000 / 1.000 / 1.000 / 1.000 PASS.
4. Button before/after computed radius capture is complete.
5. Button group computed radius capture is complete.
6. Wave 1 regression pre-check is complete.
```

---

## §12 — Plan Verdict

```txt
Phase 2 plan approved and executed.

Edited files:
  tokens.css
  components.css

Untouched during Phase 2:
  style-guide.html
  theme.json
  docs/release bookkeeping
  all lab module artifacts

Phase 3:
  Playwright + user visual QA PASS.

Phase 5:
  BACKLOG #31 closed at v3.5.9.
```
