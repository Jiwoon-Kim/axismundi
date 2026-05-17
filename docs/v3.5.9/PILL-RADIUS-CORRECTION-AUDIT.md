# v3.5.9 — Pill Radius Correction Audit

> **Status**: Phase 1 audit complete; awaiting Phase 2 baseline patch approval.  
> **Source**: BACKLOG #31.  
> **Phase 0**: `docs/v3.5.9/PILL-RADIUS-PHASE-0-REPORT.md`.  
> **Release kind**: Token graph + baseline correction.  
> **Scope**: Button + Button group morphing sources only.

---

## §0 — Audit Framing

This audit defines the v3.5.9 correction contract for morphing-safe pill
radii.

The release does not change static pill semantics:

```css
--md-sys-shape-corner-full: 9999px;
```

It adds a morphing-safe semantic and migrates only confirmed morph sources:

```txt
Button
Button group connected / selected / selected+pressed states
```

FAB, Extended FAB, static pill surfaces, Split button, Search bar, Text
field, and Ripple v2 are not migration targets.

---

## §1 — Token Contract

### §1.1 Static full token

Current token remains unchanged:

```css
--md-sys-shape-corner-full: 9999px;
```

Meaning:

```txt
Static logical full pill. Safe for surfaces that do not transition from
full pill to a smaller shape.
```

### §1.2 Morphing-safe token

Add the semantic token:

```css
--md-sys-shape-corner-pill-stable: 50%;
```

Meaning:

```txt
Semantic marker for morphing-safe full pill. This token expresses the
intent, but exact morphing geometry for button-like components should still
come from component height contracts.
```

Why `50%`:

```txt
- It is a stable CSS full-pill idiom.
- It avoids a huge numeric interpolation source.
- It remains useful as a semantic fallback for future components whose
  exact height token is unavailable.
```

Why component calc is still required:

```txt
A global token cannot know whether the component is 40px, 48px, 56px, 80px,
or a segmented-control size variant. For confirmed morphing sources, exact
radius should be finite and height-derived.
```

### §1.3 Browser support

Required syntax:

```css
calc(var(--component-height) / 2)
```

This is standard CSS math over lengths and is supported by the target browser
set used for the existing token graph. No CSS Level 4 `calc(infinity * 1px)`
or experimental value is introduced.

---

## §2 — Component-Level Calc Pattern

### §2.1 Button

Current:

```css
--comp-button-height: 40px;
--comp-button-radius: var(--md-sys-shape-corner-full);
```

Replace with:

```css
--comp-button-height: 40px;
--comp-button-radius: calc(var(--comp-button-height) / 2);
```

Result:

```txt
Rest radius becomes 20px instead of 9999px.
Pressed radius remains 8px.
Transition becomes 20px -> 8px, not 9999px -> 8px.
```

### §2.2 Button group

Button group baseline already exists and has size-aware connected variants.
Phase 2 should add button-group height/radius tokens in `tokens.css` or local
component custom properties before the §28 rules:

```css
--comp-button-group-height-xs: 32px;
--comp-button-group-height-m: 40px;
--comp-button-group-height-l:  48px;
--comp-button-group-height-xl: 56px;

--comp-button-group-pill-radius-xs: calc(var(--comp-button-group-height-xs) / 2);
--comp-button-group-pill-radius-m:  calc(var(--comp-button-group-height-m) / 2);
--comp-button-group-pill-radius-l:  calc(var(--comp-button-group-height-l) / 2);
--comp-button-group-pill-radius-xl: calc(var(--comp-button-group-height-xl) / 2);
```

If Phase 2 chooses local component vars instead of global `--comp-*` tokens,
the names should stay parallel:

```css
.ax-button-group {
  --_button-group-pill-radius: var(--comp-button-group-pill-radius-m);
}
.ax-button-group.is-size-xs {
  --_button-group-pill-radius: var(--comp-button-group-pill-radius-xs);
}
```

Final naming may be adjusted in Phase 2, but the semantic contract is fixed:

```txt
outer pill corners in morphing button-group states use finite, size-aware
pill radii rather than `corner-full`.
```

---

## §3 — Migration Steps Per Consumer

### §3.1 Button

Current declarations:

```css
/* tokens.css */
--comp-button-radius: var(--md-sys-shape-corner-full);

/* components.css */
.ax-button {
  border-radius: var(--comp-button-radius);
  transition: border-radius var(--md-sys-motion-curve-fast-spatial-duration)
    var(--md-sys-motion-curve-fast-spatial), ...;
}
.ax-button:active {
  border-radius: var(--md-sys-shape-corner-small);
}
```

Replacement:

```css
--comp-button-radius: calc(var(--comp-button-height) / 2);
```

No selector changes are required for `.ax-button`.

State matrix:

| State | Before | After | Expected |
|---|---|---|---|
| Rest | `9999px` via `corner-full` | `20px` via height/2 | Full pill for 40px height |
| Active | `8px` | `8px` | M3 pressed morph preserved |
| Transition | `9999px -> 8px` | `20px -> 8px` | Flicker removed |

### §3.2 Button group connected outer corners

Current declarations:

```css
.ax-button-group--connected label.ax-button:first-of-type,
.ax-button-group--connected button.ax-button:first-of-type {
  border-start-start-radius: var(--md-sys-shape-corner-full);
  border-end-start-radius:   var(--md-sys-shape-corner-full);
}
.ax-button-group--connected label.ax-button:last-of-type,
.ax-button-group--connected button.ax-button:last-of-type {
  border-start-end-radius: var(--md-sys-shape-corner-full);
  border-end-end-radius:   var(--md-sys-shape-corner-full);
}
```

Replacement:

```css
border-start-start-radius: var(--_button-group-pill-radius);
border-end-start-radius:   var(--_button-group-pill-radius);
border-start-end-radius:   var(--_button-group-pill-radius);
border-end-end-radius:     var(--_button-group-pill-radius);
```

Same replacement applies to the `:active` outer-edge restoration blocks.

### §3.3 Button group selected segment

Current:

```css
.ax-button-group--connected .ax-button-group__input:checked + .ax-button,
.ax-button-group--connected .ax-button[aria-pressed="true"],
.ax-button-group--connected .ax-button.is-selected {
  border-radius: var(--md-sys-shape-corner-full);
}
```

Replacement:

```css
border-radius: var(--_button-group-pill-radius);
```

Selected+pressed remains:

```css
border-radius: var(--md-sys-shape-corner-extra-small);
```

Size variants:

```txt
XS  selected pill source -> 16px  if height 32px
M   selected pill source -> 20px  if height 40px
L   selected pill source -> 24px  if height 48px
XL  selected pill source -> 28px  if height 56px
```

Phase 2 must confirm the size heights used by Button group baseline before
finalizing exact values. If the baseline does not define actual block-size
per group size, use the inherited Button size contract and document it.

---

## §4 — Backward Compatibility

Unaffected static consumers stay on `corner-full`:

```txt
Avatar
Icon button
Nav rail indicator
Search bar field
Sheet handle
Tabs / progress / slider / switch tracks
Toolbar floating container
FAB menu close/item
Date picker cells/range endpoints
```

Why this is safe:

```txt
The bug requires two things:
  1. full-pill source radius
  2. state-driven transition to a smaller radius

Static pill consumers only satisfy (1), not (2).
```

No public class names change. No markup changes are required.

---

## §5 — Wave 1 Regression Analysis

| Closed component | Impact | Regression QA |
|---|---|---|
| Button #1 | Direct impact. Rest radius source changes from 9999px to 20px. | Playwright `:active` radius trace; visual still full pill at rest. |
| Icon button #2 | No migration. Circular static pill remains `corner-full`. | Smoke check one icon button shape after validator. |
| Card #9 | No migration. Base/action card does not use `corner-full` morph source. | None beyond validator. |
| FAB family #3+#4 | No migration. Already finite radius; no border-radius transition. | Optional Playwright confirms active state radius unchanged. |
| Text field #16 | No migration. Uses extra-small / extra-small-top. | None beyond validator. |
| Search bar #17 | No migration. Search field is static `corner-full`, no radius morph. | None beyond validator. |

Button group #6:

```txt
Not a closed Wave 1 component yet, but baseline §28 already contains the
affected connected/selected morph source. v3.5.10 Button group audit should
inherit the corrected baseline.
```

---

## §6 — Validator Rule Impact

Expected validator impact:

```txt
No schema change required.
No theme.json change required.
No publish generator change required.
```

Reason:

```txt
The new token is CSS-internal and consumed by baseline CSS. It is not a
WordPress theme setting, not an ontology schema row, and not a runtime module
contract.
```

Required command:

```powershell
python .\tools\validators\validate_theme_pilot.py
```

Required result:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

---

## §7 — Motion And Accessibility

Motion:

```txt
The correction does not add animation. It changes the source value of an
existing border-radius transition from a huge sentinel to a finite radius.
```

Reduced motion:

```txt
No new reduced-motion rule is required in v3.5.9. Existing transition policy
continues to apply. If the project later disables shape morphs globally under
prefers-reduced-motion, this correction remains compatible.
```

Accessibility:

```txt
- No role/name/value changes.
- No target size changes.
- No keyboard behavior changes.
- WCAG SC 2.3 seizure/flash risk is not introduced; this is a small shape
  interpolation correction, not a flashing animation.
```

---

## §8 — Phase 2 Deliverables

Phase 2 edits exactly two baseline CSS files:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/components.css
```

No new files required.

Optional diagnostic files:

```txt
None committed. Playwright scripts/screenshots can be temporary and should
not become release artifacts.
```

Line-level patch plan:

```txt
tokens.css §4:
  add --md-sys-shape-corner-pill-stable: 50%;
  comment static full vs morphing-safe source.

tokens.css §9:
  change --comp-button-radius to calc(var(--comp-button-height) / 2).
  add Button group height/pill radius component tokens if Phase 2 chooses
  global component-token placement.

components.css §28:
  define local --_button-group-pill-radius default + size variants if not
  using direct --comp-button-group-* vars.
  replace connected/selected corner-full declarations in affected selectors.
```

---

## §9 — Phase 3 Visual QA Scenario

Button:

```txt
Before patch:
  rest radius trace includes 9999px.

After patch:
  rest radius trace = 20px.
  active radius = 8px.
  frame samples stay finite between 20px and 8px.
```

Button group:

```txt
Connected rest:
  first/last outer corners finite pill source.

Selected:
  selected full segment finite pill source.

Selected+pressed:
  inner pressed corners shrink to M3 pressed values.
  outer first/last edges stay finite pill-like.
```

Viewport matrix:

```txt
390px mobile
768px tablet
1280px desktop
```

FAB regression:

```txt
Confirm no radius change from v3.5.5 baseline.
```

---

## §10 — Out Of Scope

Explicitly not in v3.5.9:

```txt
- Changing --md-sys-shape-corner-full.
- Moving static pill surfaces to pill-stable.
- Editing theme.json.
- Editing style-guide.html by hand.
- Implementing Button group #6 Full-Spec.
- Fixing Split button.
- Editing FAB unless Phase 2 discovers unexpected evidence.
- Editing Search bar/Text field/Ripple docs.
- Adding JavaScript.
```

---

## §11 — Phase 5 Close

```txt
BACKLOG #31 -> Closed at v3.5.9.
CHANGELOG.md v3.5.9 entry added.
ROADMAP.md v3.5.9 DONE / v3.5.10 NEXT.
MODULE-STATUS-MATRIX.md receives a v3.5.9 amendment note.
Component status counts unchanged; no Wave 1 component row advances.
```

Button group note:

```txt
Button group has not closed yet. v3.5.10 Button group Phase 0 should cite
this correction as current baseline, not as a separate migration task.
```

---

## §12 — Audit Verdict

```txt
Phase 5 PASS.

The correction contract was implemented and verified:
  - add morphing-safe semantic token
  - migrate Button radius source to height/2
  - migrate Button group connected/selected morph sources to finite
    size-aware pill radii
  - preserve static corner-full semantics everywhere else
  - keep theme.json and style-guide.html untouched

Actual result:
  - Button variants trace 20px -> 8px.
  - Button group selected segment traces 20px -> 4px.
  - Button group first/last outer corners stay 20px.
  - Icon button / FAB / Text field / Search bar smoke checks show no regression.
  - Validator remains 1.000 / 1.000 / 1.000 / 1.000 PASS.
```
