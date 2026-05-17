# v3.5.10 — Wave 1 — Button Group #6 Phase 0 Report

> **Status**: Phase 0 complete.  
> **Component**: Button group #6.  
> **Category decision**: Component Full-Spec.  
> **Audit shape decision**: 3-doc trio.  
> **Scope**: Documentation-only. No baseline or module implementation.

---

## §0 — Phase 0 Framing

Button group is Wave 1's next Action component after the v3.5.9 pill-radius
baseline correction.

This Phase 0 resolves four ontology questions:

```txt
1. Is Button group its own component or a family merge?
2. Does it need a runtime audit?
3. Does Button group remain a ripple CANDIDATE or become TARGET?
4. Does the v3.5.9 local finite pill radius stay local or become a public
   Button group token?
```

Verdict:

```txt
Button group is a standalone Component Full-Spec cycle.
It uses a 3-doc audit trio.
It inherits v3.5.9 finite pill baseline as CURRENT.
Ripple should be promoted from CANDIDATE to TARGET for bounded per-segment
enhancement in Phase 2.
Button group public token promotion is deferred to Phase 1 audit, with local
v3.5.9 radius remaining current for Phase 0.
```

---

## §1 — Authoritative Inputs

Read during Phase 0:

```txt
Root control:
  AGENTS.md
  CURRENT-STATE.md
  NEXT-SESSION.md

Framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md

Current baseline:
  products/reference-implementations/axismundi-lab/stylesheets/components.css §28
  products/reference-implementations/axismundi-lab/style-guide.html #components-button-group
  products/reference-implementations/axismundi-lab/stylesheets/tokens.css
  products/reference-implementations/axismundi-lab/stylesheets/blocks.css

Prior release:
  docs/v3.5.9/PILL-RADIUS-CORRECTION-AUDIT.md
  docs/v3.5.9/PILL-RADIUS-PHASE-0-REPORT.md
  docs/v3.5.9/PILL-RADIUS-PHASE-2-PLAN.md
```

External references checked with Playwright because the public M3 pages are
JavaScript-rendered:

```txt
https://m3.material.io/components/button-groups/overview
https://m3.material.io/components/button-groups/specs
https://m3.material.io/components/button-groups/guidelines
https://m3.material.io/components/button-groups/accessibility
```

Relevant M3 findings:

```txt
- Button groups are a May 2025 M3 Expressive component.
- Variants: standard and connected.
- Connected button groups replace the baseline segmented button.
- Supported configurations: XS/S/M/L/XL, round/square, single-select,
  multi-select, selection-required.
- Button groups can contain buttons and icon buttons.
- Web implementation is marked unavailable in the M3 resource table.
- Accessibility guidance: each item should be at least 48x48dp, focus lands
  on the first button rather than the container, Tab moves through items,
  Space/Enter activates.
```

---

## §2 — Baseline Inventory: `components.css §28`

Source range:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
  §28 Button group, around lines 4512-4840
```

Existing component root:

```css
.ax-button-group {
  --_button-group-pill-radius: calc(var(--comp-button-height) / 2);
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
}
```

Existing variants:

```txt
Standard:
  .ax-button-group
  per-button rounded, individually spaced
  selected button widens via flex-grow: 1.15

Connected:
  .ax-button-group--connected
  2px gap
  outer first/last corners are finite pill after v3.5.9
  inner corners vary by size/state
```

Existing semantic patterns:

```txt
Pattern A — radio + label:
  <fieldset class="ax-button-group">
  <input type="radio" class="ax-button-group__input">
  <label class="ax-button ...">

Pattern B — button + aria-pressed:
  <div class="ax-button-group ax-button-group--connected" role="toolbar">
  <button type="button" class="ax-button ..." aria-pressed="true|false">
```

Existing hidden input strategy:

```css
.ax-button-group__input {
  position: absolute;
  inline-size: 1px;
  block-size: 1px;
  margin: -1px;
  padding: 0;
  border: 0;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
  pointer-events: none;
}
```

Existing selection triggers:

```css
.ax-button-group .ax-button-group__input:checked + .ax-button,
.ax-button-group .ax-button[aria-pressed="true"],
.ax-button-group .ax-button.is-selected
```

Existing focus/disabled:

```txt
Pattern A focus:
  input:focus-visible + .ax-button surfaces the ring on the visual label.

Pattern A disabled:
  input:disabled + .ax-button dims the label and disables pointer events.

Pattern B disabled:
  inherited from Button baseline via button:disabled.
```

Existing connected geometry:

```txt
Rest:
  M default inner corners = 8px.
  XS inner corners = 4px.
  L inner corners = 16px.
  XL inner corners = 20px.

Pressed:
  M -> 4px.
  L -> 12px.
  XL -> 16px.

Selected:
  selected segment uses finite pill radius after v3.5.9.
```

No `blocks.css` bridge currently exists for Button group:

```txt
rg found no .ax-button-group / button-group mapping in blocks.css.
core/buttons maps Button family generally, not Button group specifically.
```

---

## §3 — Public Specimen Inventory: `style-guide.html`

Source range:

```txt
products/reference-implementations/axismundi-lab/style-guide.html
  #components-button-group, around lines 1019-1114
```

Existing specimens:

```txt
1. Standard — single-select
   fieldset + radio + label
   Day / Week / Month
   selected button widens

2. Connected — single-select
   fieldset + radio + label
   left / center / right labels
   2px gap + connected geometry

3. Toolbar — multi-toggle
   div[role=toolbar] + button[aria-pressed]
   B / I / U / S text formatting demo
```

Specimen limitations:

```txt
- No icon-only segment.
- No icon+label segment.
- No explicit disabled specimen.
- No 2-segment / 4-segment / 5-segment comparison.
- No ripple v2 specimen.
- No Button group module pattern page yet.
```

These are Phase 1/2 work, not baseline defects.

---

## §4 — Category Decision

Decision:

```txt
Button group = Component Full-Spec.
```

Applicable gates:

```txt
G1-G10.
G11-G16 do not apply unless a future phase discovers a real extracted runtime.
```

Rationale:

```txt
- Matrix row #6 already classifies Button group as Component Full-Spec.
- Existing baseline is native-control based.
- Pattern A uses real radio inputs.
- Pattern B uses real buttons with aria-pressed.
- There is no reusable `button-group/` runtime provider.
- The inline style-guide toggle snippet is an integrator behavior example,
  not a reusable Interaction Runtime Module.
```

This aligns with Text field v3.5.7:

```txt
Native/CSS interaction -> 3-doc trio, not RUNTIME-AUDIT.
```

It does not align with Search bar v3.5.8:

```txt
Extracted JS runtime -> 4-doc shape.
```

---

## §5 — Audit Shape Decision

Decision:

```txt
Use 3-doc trio:
  BUTTON-GROUP-SPEC-AUDIT.md
  BUTTON-GROUP-MEASUREMENT-AUDIT.md
  BUTTON-GROUP-WP-MAPPING.md

Do not create:
  BUTTON-GROUP-RUNTIME-AUDIT.md
```

Why no runtime audit:

```txt
Pattern A:
  Native radio group behavior is supplied by the browser.

Pattern B:
  aria-pressed toggling is integrator behavior, similar to simple toolbar
  command wiring. It is not a reusable runtime provider in this repo.
```

Phase 1 SPEC must still document:

```txt
- Pattern A / Pattern B decision tree.
- Keyboard semantics per pattern.
- Where integrator JS begins.
- Why no runtime audit exists.
```

---

## §6 — Terminology Decision

Decision:

```txt
Canonical Axismundi name:
  Button group

Mapping language:
  segmented-like
  connected button group
  M3 Expressive replacement for segmented button
```

Do not create a separate `segmented-button/` module.

Rationale:

```txt
- Matrix row #6 is Button group.
- Baseline §28 is Button group.
- style-guide anchor is #components-button-group.
- M3 docs say connected button groups replace the baseline segmented button,
  which supports mapping language but not a new Axismundi component row.
```

---

## §7 — Native Semantics Decision Tree

Decision:

```txt
Pattern A — single-select / value choice:
  fieldset + legend + input[type=radio] + label.ax-button

Pattern B — multi-toggle / independent command toggle:
  div[role=toolbar] + button[type=button][aria-pressed]
```

Use Pattern A when:

```txt
- Exactly one option is selected.
- The selected value is part of a form or persistent preference.
- Native radio keyboard / submit / reset behavior is desired.
```

Use Pattern B when:

```txt
- Each segment toggles independently.
- Semantics are commands or formatting states.
- `aria-pressed` is required to expose toggle state.
```

Accessibility tension to record:

```txt
M3 accessibility guidance says the group container is not focusable, focus
lands on the first button, Tab moves through items, and Space/Enter activates.

Native radio groups commonly use Tab to enter the group and arrow keys between
radio options. Axismundi Pattern A intentionally favors native radio semantics
for single-select value controls.
```

Disposition:

```txt
Phase 1 SPEC must document both patterns honestly:
  - M3 button-item guidance for button-based groups.
  - Native radio behavior for CSS-only single-select groups.
```

This is not a blocker because the project already differentiates native
semantics by actual control type.

---

## §8 — Dependency Profile / Consumer-State

State-aware dependency profile:

```txt
components.css §0 state-layer foundation:
  CURRENT

ripple/:
  TARGET, bounded, per segment

icon-system/:
  CURRENT conditional

pill-stable token:
  CURRENT baseline correction inherited from v3.5.9
```

Ripple decision:

```txt
Promote Button group from ripple CANDIDATE to TARGET in v3.5.10.
```

Rationale:

```txt
- v3.5.6 matrix left Button group as CANDIDATE pending component cycle.
- This is the component cycle.
- Each segment is a button-like action/selection surface.
- Segment geometry is bounded, not unbounded.
- Ripple should attach to individual segments, not to the group container.
```

Implementation implication:

```html
<label class="ax-button ..." data-ax-ripple="bounded">...</label>
<button class="ax-button ..." data-ax-ripple="bounded">...</button>
```

Phase 2 should verify whether labels are acceptable ripple hosts. If the
current Ripple v2 host policy should avoid labels, the pattern page may use
button-based specimens for ripple demos while Pattern A remains valid for
radio semantics. This is a Phase 2 implementation detail, not a Phase 0
blocker.

Icon-system decision:

```txt
CURRENT conditional.
```

Rationale:

```txt
Button group can contain:
  - label-only segments
  - icon + label segments
  - icon-only segments

Only icon-bearing segments consume icon-system.
```

---

## §9 — v3.5.9 Pill Radius Baseline Inheritance

Decision:

```txt
v3.5.9 finite pill correction is CURRENT baseline for Button group.
Do not re-litigate BACKLOG #31.
```

Current token state:

```css
--md-sys-shape-corner-full: 9999px;
--md-sys-shape-corner-pill-stable: 50%;
--comp-button-radius: calc(var(--comp-button-height) / 2);
```

Current Button group local state:

```css
.ax-button-group {
  --_button-group-pill-radius: calc(var(--comp-button-height) / 2);
}
```

Phase 0 token promotion decision:

```txt
Do not promote to public --comp-button-group-* tokens in Phase 0.
```

Phase 1 must decide between:

```txt
Option A — keep local variable
  Pros: honors v3.5.9 conservative patch; no premature public token.
  Cons: Button group Full-Spec may deserve public component tokens.

Option B — public Button group tokens
  Example:
    --comp-button-group-container-height-m
    --comp-button-group-pill-radius-m
  Pros: full-spec token surface becomes explicit.
  Cons: adds token graph before Phase 2 visual evidence.

Option C — hybrid
  Public token for default M only, local overrides for future sizes.
  Pros: controlled public surface.
  Cons: half-step may age poorly.
```

Recommendation:

```txt
Phase 1 should evaluate Option A vs B.
Default lean: Option A through Phase 2 unless size variants force public tokens.
```

---

## §10 — Variant / Size / Content Matrix

Phase 1 should audit:

```txt
Structure:
  standard
  connected

Segment count:
  2-segment
  3-segment
  4-segment
  5-segment

Selection mode:
  single-select
  multi-toggle
  selection-required

Content:
  label-only
  icon + label
  icon-only

Visual style:
  filled
  tonal
  outlined
  elevated
  text

Size:
  XS
  S
  M
  L
  XL

State:
  rest
  hover
  focus-visible
  pressed
  selected
  selected + pressed
  disabled
```

Phase 2 should not render every cartesian combination. Recommended pattern
HTML scope:

```txt
1. Standard 3-segment single-select, label-only.
2. Connected 3-segment single-select, label-only.
3. Connected 4-segment toolbar multi-toggle.
4. Icon + label connected group.
5. Icon-only connected group.
6. 2-segment compact case.
7. 5-segment overflow/spacing case.
8. Size comparison row.
9. Disabled split.
10. Ripple bounded specimen.
```

---

## §11 — Disabled-State Split

Decision:

```txt
Use Button family Pattern A:
  §5 native disabled
  §5a aria-disabled plugin-managed
```

Native disabled:

```txt
Pattern A:
  input:disabled + label.ax-button

Pattern B:
  button:disabled
```

Aria-disabled plugin-managed:

```txt
Use when integrator must keep focusability or apply custom activation policy.
Pattern page should mark it explicitly as plugin/integrator-managed.
```

Do not use Card-style 3-way split:

```txt
Card has static article surfaces.
Button group is a control surface.
```

---

## §12 — WCAG SC List

Phase 1 MEASUREMENT should cite:

```txt
SC 2.5.8 Target Size (Minimum) AA
  Expected PASS for segments with at least 24x24 CSS px target.

SC 2.5.5 Target Size (Enhanced) AAA
  M3 accessibility page says each button in a group should have 48x48dp.
  Baseline connected XS/S enforce min-inline-size: 48px; Phase 1 must verify
  all displayed specimens.

SC 4.1.2 Name, Role, Value
  Pattern A: radio name/value/checked state.
  Pattern B: button role + aria-pressed state.

SC 1.4.3 Contrast (Minimum)
  selected / unselected / disabled text contrast.

SC 1.4.11 Non-text Contrast
  focus ring, outline, selected indicator, connected boundaries.

SC 2.1.1 Keyboard
  Pattern A: native radio keyboard behavior.
  Pattern B: Tab + Space/Enter activation.

SC 3.2.2 On Input
  Selecting a radio should not unexpectedly submit or navigate.
```

---

## §13 — WordPress Mapping Stub

Current finding:

```txt
blocks.css has no Button group bridge.
```

Candidate paths:

```txt
Path A — core/buttons composition
  Use core/buttons + individual core/button blocks as loose approximation.
  Good for unconnected button rows.
  Weak for connected geometry and single-select semantics.

Path B — pattern composition
  Theme pattern renders Button group markup for view/sort/filter controls.
  Good for static/pattern-driven UI.

Path C — plugin/editor territory
  Real filtering, saved preferences, editor toolbar behavior, and formatting
  commands belong outside theme baseline.

Path D — future block style / variation
  Possible only after WP mapping audit proves a stable core/* hook.
```

Recommendation:

```txt
Phase 1 WP-MAPPING should treat core/buttons as a partial visual mapping,
not a complete semantic mapping. Button group's native radio / aria-pressed
contracts likely require pattern or plugin composition.
```

---

## §14 — Risks + Dispositions

| Risk | Description | Disposition |
|---|---|---|
| R1 Runtime over-shaping | Interactive states may tempt RUNTIME-AUDIT. | 3-doc trio; no extracted runtime found. |
| R2 Terminology drift | "Segmented button" may create duplicate module. | Button group canonical; segmented is mapping language. |
| R3 Split button bleed | Split button shares connected geometry. | Row #7 deferred; no dropdown/popover work. |
| R4 Ripple promotion | Matrix had Button group as CANDIDATE. | Promote to bounded TARGET for per-segment Phase 2. |
| R5 Label ripple host | Pattern A labels may not be ideal ripple hosts. | Phase 2 must verify; may demo ripple on button pattern only. |
| R6 Icon-system overstatement | Not all segments have icons. | CURRENT conditional. |
| R7 Token public-surface creep | Full-Spec may tempt public Button group tokens. | Phase 1 decides; v3.5.9 local var is current. |
| R8 Disabled ambiguity | Group-level vs segment-level disabled. | Button family Pattern A; Phase 1 formalizes. |
| R9 M3 keyboard tension | M3 says Tab through buttons; native radios use arrow keys inside group. | Record per-pattern semantics honestly. |
| R10 WP overmapping | core/buttons may look close but lacks semantics. | WP-MAPPING treats as partial visual mapping only. |

---

## §15 — Phase 1 Entry Conditions

Phase 1 may start when this report is approved.

Expected Phase 1 deliverables:

```txt
products/reference-implementations/axismundi-lab/modules/button-group/docs/
  BUTTON-GROUP-SPEC-AUDIT.md
  BUTTON-GROUP-MEASUREMENT-AUDIT.md
  BUTTON-GROUP-WP-MAPPING.md
```

Phase 1 must decide:

```txt
1. Whether to keep `--_button-group-pill-radius` local or introduce public
   Button group component tokens.
2. Exact size-scope for XS/S/M/L/XL.
3. Exact ripple host strategy for Pattern A labels vs Pattern B buttons.
4. Exact disabled-state split and captions.
5. WordPress mapping path for core/buttons vs pattern/plugin territory.
```

Phase 1 must not create:

```txt
lab-button-group.css
lab-button-group.js
lab-button-group-pattern.html
BUTTON-GROUP-RUNTIME-AUDIT.md
```

---

## §16 — G1-G10 Applicability

| Gate | Applicability | Phase |
|---|---|---|
| G1 validator PASS | Applicable | all phases |
| G2 baseline untouched during module work | Applicable | Phase 1+ |
| G3 publish runs cleanly | Applicable | Phase 5 |
| G4 module artifacts present | Applicable | Phase 2 |
| G5 CHANGELOG entry | Applicable | Phase 5 |
| G6 Static Visual QA | Applicable | Phase 3 |
| G7 Principle 1 | Applicable: real controls only | Phase 1-3 |
| G8 Principle 2 | Applicable: native semantics per pattern | Phase 1-3 |
| G9 WCAG SC citations accurate | Applicable | Phase 1 |
| G10 3-doc audit pattern complete | Applicable | Phase 1 |

G11-G16:

```txt
Not applicable for v3.5.10 unless Phase 1 discovers a real extracted runtime.
Current decision: no runtime audit.
```

---

## §17 — Non-Goals

Explicitly not in v3.5.10 Phase 0:

```txt
- Implement Button group.
- Create lab/modules/button-group/.
- Create Phase 1 audit docs before approval.
- Create BUTTON-GROUP-RUNTIME-AUDIT.md.
- Edit components.css.
- Edit tokens.css.
- Change --md-sys-shape-corner-pill-stable.
- Reopen BACKLOG #31.
- Implement Split button.
- Implement Toolbar.
- Implement standalone toggle button.
- Add JavaScript.
- Edit theme.js.
- Edit style-guide.html.
- Edit blocks.css.
- Edit CHANGELOG / ROADMAP / BACKLOG / CURRENT-STATE / NEXT-SESSION.
- Promote actual ripple wiring before Phase 2.
```

---

## §18 — Verdict

```txt
Phase 0 PASS.

Button group #6 is ready for Phase 1 planning/execution as a Component
Full-Spec module with a 3-doc audit shape.

Decisions locked:
  - Button group canonical name; segmented wording is mapping language only.
  - Split button remains out of scope.
  - Pattern A radio+label and Pattern B button+aria-pressed are both valid.
  - Ripple moves from CANDIDATE to intended TARGET, bounded per segment.
  - icon-system is CURRENT conditional.
  - v3.5.9 finite pill correction is current baseline.
  - Public Button group token promotion is deferred to Phase 1 audit.

Next:
  Phase 1 audit docs.
```

