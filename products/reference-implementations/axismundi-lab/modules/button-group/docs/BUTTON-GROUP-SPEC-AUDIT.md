# Button Group — Spec Audit (v3.5.10 Phase 5 close)

> **Component**: Button group #6  
> **Category**: Component Full-Spec  
> **Status**: Phase 5 close — v3.5.10 DONE. Lab artifacts implemented and Phase 3 visual QA PASS.  
> **Phase 0 source**: `docs/v3.5.10/BUTTON-GROUP-PHASE-0-REPORT.md`  
> **Companions**: `./BUTTON-GROUP-MEASUREMENT-AUDIT.md`, `./BUTTON-GROUP-WP-MAPPING.md`

---

## §0 — Status / Scope

This audit promotes Button group into the Wave 1 Component Full-Spec lane.

```txt
In scope:
  - Standard button group
  - Connected button group
  - Pattern A: radio + label single-select
  - Pattern B: button + aria-pressed multi-toggle
  - Label-only / icon+label / icon-only segment content
  - v3.5.9 finite pill baseline inheritance
  - ripple/ TARGET bounded per segment
  - icon-system/ CURRENT conditional

Out of scope:
  - Split button #7
  - Toolbar #8
  - standalone toggle button
  - Button group JS runtime extraction
  - v3.5.9 token redesign
```

Button group is not a family-merge cycle. It is a single matrix row, with a
single 3-doc audit set.

---

## §1 — Authoritative Inputs

```txt
Phase documents:
  docs/v3.5.10/BUTTON-GROUP-PHASE-0-PLAN.md
  docs/v3.5.10/BUTTON-GROUP-PHASE-0-REPORT.md

Framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md

Current baseline:
  products/reference-implementations/axismundi-lab/stylesheets/components.css §28
  products/reference-implementations/axismundi-lab/style-guide.html #components-button-group
  products/reference-implementations/axismundi-lab/stylesheets/tokens.css

Precedents:
  ../button/docs/BUTTON-SPEC-AUDIT.md
  ../icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
  ../fab/docs/FAB-SPEC-AUDIT.md
  docs/v3.5.9/PILL-RADIUS-CORRECTION-AUDIT.md
```

External references:

```txt
M3 Button groups overview / specs / guidelines / accessibility pages.
The pages are JavaScript-rendered; Phase 0 used Playwright extraction.
```

---

## §2 — Component Identity

Canonical name:

```txt
Button group
```

Mapping language:

```txt
segmented-like
connected button group
M3 Expressive replacement for segmented button
```

Do not create:

```txt
segmented-button/
segmented-control/
```

Rationale:

```txt
M3 now treats connected button groups as the replacement for baseline
segmented buttons, but Axismundi already has a canonical matrix row and
baseline section named Button group. The external term explains mapping;
it does not create a new component.
```

---

## §3 — M3 Spec Digest

Phase 0 Playwright extraction from the official M3 pages recorded:

```txt
Variants:
  standard button group
  connected button group

Configurations:
  XS / S / M / L / XL
  round / square default shape
  single-select
  multi-select
  selection-required

Content:
  label buttons
  label buttons + icon buttons
  icon-only buttons

Behavior:
  standard groups affect adjacent button width/shape
  connected groups keep adjacent width stable
  selected items change shape and may change color

Accessibility:
  each button should expose at least a 48x48dp target
  the group container is not the focus target
  each item must be labeled
  Tab / Space / Enter are the documented button-item flow
```

Axismundi alignment:

```txt
Standard group:
  baseline supports selected flex-grow widening.

Connected group:
  baseline supports 2px gap, outer finite pill corners, and inner morphing.

Selection:
  baseline supports native radio single-select and aria-pressed multi-toggle.
```

Known tension:

```txt
M3 accessibility guidance describes item-by-item button navigation.
Axismundi Pattern A uses native radio controls, where Tab enters the group and
arrow keys move between radio options. This is intentional for value-choice
semantics and must be documented rather than hidden.
```

---

## §4 — Variant Matrix

The audit recognizes these axes:

| Axis | Values | Phase 1 decision |
|---|---|---|
| Structure | standard / connected | Both in scope |
| Segment count | 2 / 3 / 4 / 5 | All audited; representative Phase 2 demos |
| Selection | single-select / multi-toggle / selection-required | In scope |
| Content | label-only / icon+label / icon-only | In scope |
| Style | filled / tonal / outlined / elevated / text | In scope through nested Button styles |
| Size | XS / S / M / L / XL | Audit all; Phase 2 may demo representative set |
| State | rest / hover / focus / pressed / selected / selected+pressed / disabled | In scope |

Phase 2 should avoid a full cartesian explosion. The pattern page should show
representative combinations sufficient to prove the contract.

---

## §5 — Markup Pattern Matrix

### §5.1 Pattern A — Radio + Label

Use for single-select value choice:

```html
<fieldset class="ax-button-group ax-button-group--connected">
  <legend class="u-vh">View mode</legend>
  <input type="radio" name="view" id="view-list"
         class="ax-button-group__input">
  <label for="view-list"
         class="ax-button is-filled has-state-layer">List</label>
</fieldset>
```

Contract:

```txt
- The radio input owns checked state.
- The label is the visible button surface.
- Native form submission and reset work.
- Native radio keyboard behavior applies.
- No JS required for state changes.
```

### §5.2 Pattern B — Button + `aria-pressed`

Use for independent toggle actions:

```html
<div class="ax-button-group ax-button-group--connected"
     role="toolbar"
     aria-label="Text formatting">
  <button type="button"
          class="ax-button is-tonal has-state-layer"
          aria-pressed="true">B</button>
</div>
```

Contract:

```txt
- Each segment is a real button.
- aria-pressed exposes toggle state.
- Integrator JS flips aria-pressed.
- Tab moves through buttons; Space/Enter activates the focused button.
```

### §5.3 Pattern Decision Rule

```txt
If the group represents one selected value:
  use Pattern A.

If each segment independently toggles a command:
  use Pattern B.

If clicking a segment opens a menu:
  use Split button / Menu / Toolbar work, not Button group.
```

---

## §6 — M3 Web Guidance vs Native Radio Semantics

This audit records a deliberate dual-pattern contract.

M3 accessibility guidance says:

```txt
- the group container is not focusable
- focus lands on the first button
- Tab navigates to the next button
- Space or Enter activates the focused button
```

That maps directly to Pattern B.

Pattern A instead uses native radios:

```txt
- Tab enters the radio group
- arrow keys move between options
- checked state is native
- form submission/reset is native
```

Phase 3 Playwright observation:

```txt
Chrome native radio behavior changes checked state with ArrowLeft /
ArrowRight in the lab Pattern A specimens. Home / End do not change checked
state in this native browser flow. v3.5.10 does not add JS to Pattern A to
polyfill Home / End because Pattern A is intentionally native/no-JS.
```

Verdict:

```txt
Both patterns are valid because they represent different semantics.
Do not flatten Pattern A into buttons only to mimic M3 web guidance.
Do not use radios for independent toolbar toggles.
```

This is a Principle 2 decision: semantics follow the actual interaction model,
not the visual shape.

---

## §7 — Geometry Contract

Current baseline geometry:

```txt
Standard group:
  gap = var(--space-sm)
  selected segment flex-grow = 1.15

Connected group:
  gap = 2px
  M default inner corner = 8px
  M pressed inner corner = 4px
  selected segment = finite pill after v3.5.9
```

Phase 3 size observation:

```txt
The current `.is-size-xs`, `.is-size-s`, `.is-size-l`, and `.is-size-xl`
hooks in baseline §28 do not change segment height, typography, or horizontal
padding. They currently affect gap / min-inline-size / connected corner
rules only.

This is consistent with Button group's dependency on child Button / Icon
button size variants: Button #1 shipped the 40px baseline size only, with
XS/M/L/XL deferred. v3.5.10 therefore treats Button group size support as
partial, not complete.
```

v3.5.9 inheritance:

```txt
--_button-group-pill-radius: calc(var(--comp-button-height) / 2)
```

Verified result:

```txt
selected segment rest: 20px
selected+pressed:      4px inner corner
outer first/last:      20px preserved
```

Do not change `--md-sys-shape-corner-pill-stable` in v3.5.10.

---

## §8 — Dependency Statement

```txt
components.css §0 state-layer foundation:
  CURRENT

ripple/:
  TARGET, bounded, per segment

icon-system/:
  CURRENT conditional

v3.5.9 pill radius correction:
  CURRENT baseline
```

Ripple:

```txt
Button group moves from v3.5.6 CANDIDATE to v3.5.10 intended TARGET.
Attach ripple to each visible segment, not to the group container.
Default variant: bounded.
```

Open Phase 2 implementation question:

```txt
Pattern A labels may need special verification as ripple hosts.
If labels are not acceptable ripple hosts, the pattern page should show
ripple wiring on Pattern B button specimens while preserving Pattern A as
the native single-select contract.
```

Icon-system:

```txt
Label-only segment:
  no icon-system dependency

Icon+label or icon-only segment:
  consumes Material Symbols / icon-system
```

---

## §9 — Disabled State Contract

Use Button family Pattern A:

```txt
§5 native disabled
§5a aria-disabled plugin-managed
```

Native disabled:

```html
<input type="radio" class="ax-button-group__input" disabled>
<label class="ax-button ...">Week</label>

<button type="button" class="ax-button ..." disabled>Bold</button>
```

Aria-disabled plugin-managed:

```html
<button type="button"
        class="ax-button ..."
        aria-disabled="true">Locked</button>
```

Rules:

```txt
Use native disabled when the segment should be removed from activation.
Use aria-disabled only when the integrator keeps focusability and blocks
activation in code.
Group-level disabled must be expressed by disabling each child control or by
an explicit plugin-managed wrapper policy; the visual group container alone
does not disable its children.
```

---

## §10 — Icon Segment Contract

Icon-bearing segments use the established Material Symbols pattern:

```html
<span class="material-symbols-rounded notranslate ax-button-icon"
      translate="no"
      aria-hidden="true"
      draggable="false">view_list</span>
```

Icon-only segments require accessible names:

```html
<button type="button"
        class="ax-button is-tonal has-state-layer"
        aria-label="List view"
        data-ax-ripple="bounded">
  <span class="material-symbols-rounded notranslate ax-button-icon"
        translate="no" aria-hidden="true">view_list</span>
</button>
```

Decision:

```txt
icon-system/ = CURRENT conditional.
```

This mirrors Button #1 rather than Icon button #2:

```txt
Button #1:
  icon-system conditional

Icon button #2:
  icon-system unconditional

Button group #6:
  icon-system conditional
```

---

## §11 — Anti-Patterns

Do not:

```txt
- Use Button group for tabs.
- Use Button group for split button dropdowns.
- Use Button group as a Toolbar replacement.
- Use div role="button" for segments.
- Use radio inputs for independent toolbar toggles.
- Use aria-pressed for mutually-exclusive form values without a reason.
- Put data-ax-ripple on the group container instead of each segment.
- Remove native radio inputs from Pattern A.
- Hide radio inputs with display:none.
- Use icon-only segments without aria-label.
- Reopen v3.5.9 pill-stable token value.
- Create segmented-button/ as a parallel module.
```

---

## §12 — Phase 5 Verdict Criteria

SPEC criteria:

```txt
#1 M3 spec coverage
   PASS at Phase 5 when standard + connected variants, configurations, states,
   and accessibility tension are documented and implemented in pattern HTML.

#2 Token-driven implementation
   PASS at Phase 5 when Phase 2 consumes existing tokens and any public
   Button group tokens are justified or explicitly deferred.

#3 Pattern HTML completeness
   PASS at Phase 2. `lab-button-group-pattern.html` demonstrates Pattern A/B,
   connected geometry, icons, disabled, and bounded ripple.

#4 Audit doc completeness
   PASS at Phase 1 when SPEC / MEASUREMENT / WP-MAPPING cross-reference.

#5 Dependency declarations
   PASS at Phase 1 when state-layer/ripple/icon-system/pill correction states
   are explicit.
```

Current Phase 1 verdict:

```txt
#4 Audit doc completeness      PASS
#5 Dependency declarations     PASS
#3 Pattern HTML completeness   PASS at Phase 2
#1-#2                          deferred to Phase 5
```

Phase 5 verdict (v3.5.10 close):

```txt
#1 M3 spec coverage             PASS — standard + connected variants, Pattern A/B,
                                 disabled, bounded ripple, M3 vs native radio tension
                                 all documented and implemented in pattern HTML.
                                 SIZE COVERAGE PARTIAL: M3 XS/S/M/L/XL sizing not
                                 fully implemented (segment height stays 40px across
                                 size hooks). Tracked as BACKLOG #32 — Button family
                                 size variants cycle.
#2 Token-driven implementation  PASS — Phase 2 consumed existing tokens. Public
                                 Button group tokens explicitly NOT promoted in
                                 v3.5.10; the local --_button-group-pill-radius
                                 inherited from v3.5.9 remains the only addition.
#3 Pattern HTML completeness    PASS — Phase 2 close.
#4 Audit doc completeness       PASS.
#5 Dependency declarations      PASS.

Honest findings carried forward:
  - SC 2.5.5 AAA NOT PASS for default M (40px < 44px), same as Button #1 precedent.
  - Size hooks (is-size-xs/s/l/xl) exist in baseline §28 as partial scaffolding;
    full size implementation deferred to BACKLOG #32.
  - Pattern A Home/End does not change checked state in Chrome native radio flow.
    Acceptable because Pattern A is native/no-JS and does not polyfill browser
    radio behavior.
```

---

## §13 — G1-G10 Mapping

| Gate | Status at Phase 5 | Notes |
|---|---|---|
| G1 validator | PASS | validator remains 1.000 / 1.000 / 1.000 / 1.000 |
| G2 baseline untouched | PASS | Lab artifacts only; components.css §28 inherited from v3.5.9 |
| G3 publish | PASS | Phase 5 mechanical close complete |
| G4 module artifacts | PASS | lab-button-group.css / .js / -pattern.html shipped |
| G5 changelog | PASS | v3.5.10 entry in CHANGELOG.md |
| G6 static visual QA | PASS | Phase 3 Playwright + user QA — see SPEC §12 Phase 5 verdict |
| G7 Principle 1 | PASS | Real radio + label (Pattern A) and real button + aria-pressed (Pattern B) |
| G8 Principle 2 | PASS | Semantics split by Pattern A/B; no role override |
| G9 WCAG citations | PASS | See MEASUREMENT companion; SC 2.5.5 AAA honest NOT PASS recorded |
| G10 3-doc audit pattern | PASS | SPEC + MEASUREMENT + WP-MAPPING trio, no RUNTIME |

G11-G16:

```txt
Not applicable. No Button group runtime audit.
```

---

## §14 — What This Audit Does NOT Do

```txt
- Does not implement lab-button-group.css.
- Does not implement lab-button-group-pattern.html.
- Does not add lab-button-group.js.
- Does not edit components.css.
- Does not edit tokens.css.
- Does not edit style-guide.html.
- Does not edit blocks.css.
- Does not implement Split button.
- Does not implement Toolbar.
- Does not change v3.5.9 pill-stable token.
- Does not register WordPress block styles.
```

---

## §15 — v3.5.13 Size-Variant Alignment Note

This additive note links Button group #6 to BACKLOG #32. It does not reopen the
v3.5.10 release verdict.

Current state:

```txt
Button group exposes `is-size-*` hooks, but v3.5.10 Phase 3 verified those
hooks do not yet change rendered segment height or label typography because
segments inherit the single-size Button baseline.
```

v3.5.13 size contract:

```txt
BUTTON-FAMILY-SIZE-AUDIT.md locks Option C:
  public Button-family size tokens + local connected Button group geometry.
```

Expected Phase 2 impact:

```txt
Button group should consume Button size tokens for segment height/padding/icon
size while keeping connected outer/inner radius behavior local to §28.
```

Non-goal:

```txt
This note does not change Pattern A/B semantics, add Split button, or alter the
v3.5.10 Component Full-Spec verdict.
```
