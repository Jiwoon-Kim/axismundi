# Text Field — Measurement Audit (v3.5.7 Phase 1)

> **Status**: Phase 1 measurement audit body authored. Implementation not started.  
> **Component**: Text field #16  
> **Category**: Component Full-Spec + Interaction  
> **Companions**: `TEXT-FIELD-SPEC-AUDIT.md`, `TEXT-FIELD-WP-MAPPING.md`

---

## §0 — Status / Scope

This audit records current Text field measurements and Phase 2/3 measurement requirements.

Documentation-only boundary:

```txt
No lab-text-field.css.
No lab-text-field-pattern.html.
No lab-text-field.js.
No baseline edits.
```

Primary measurement authority:

```txt
components.css §9 Text field
style-guide.html #components-text-field
```

External reference:

```txt
M3 Text fields specs/guidelines
Material Web text field as secondary comparison only
```

---

## §1 — Inputs Read

Cycle docs:

```txt
docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md
docs/v3.5.7/TEXT-FIELD-PHASE-1-PLAN.md
```

Baseline files:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
```

Companion docs:

```txt
TEXT-FIELD-SPEC-AUDIT.md
TEXT-FIELD-WP-MAPPING.md
```

---

## §2 — Baseline Measurement Inventory

Core private values:

| Token / rule | Current value | Role |
|---|---:|---|
| `--_tf-h` | `56px` | single-line container min-height |
| `--_tf-px` | `var(--space-md)` | container horizontal padding |
| `--_tf-label-rest-size` | body-large size | rest label |
| `--_tf-label-rest-lh` | body-large line-height | rest label |
| `--_tf-label-float-size` | body-small size | floated label |
| `--_tf-label-float-lh` | body-small line-height | floated label |
| `textarea.text-field__input` min-height | `96px` | multiline field |

Container layout:

```txt
.text-field:
  display flex
  flex-direction column
  width 100%

.text-field__container:
  display grid
  grid-template-columns: auto auto 1fr auto auto
  min-height: 56px
  padding-inline: var(--space-md)
  column-gap: var(--space-sm)
```

Grid columns:

```txt
1 leading icon
2 prefix
3 input + label
4 suffix
5 trailing icon + error icon
```

---

## §3 — M3 Measurement Comparison

Phase 1 classification:

```txt
Baseline follows the standard M3 single-line 56px Text field container
model for filled and outlined variants.
```

Current M3 comparison summary:

| M3 measurement area | Axismundi current state | Verdict |
|---|---|---|
| Filled container height | 56px min-height | PASS at baseline level |
| Outlined container height | 56px min-height | PASS at baseline level |
| Floating label scale | body-large to body-small | PASS at baseline level |
| Active indicator | 1px rest to 2px focus | PASS at baseline level |
| Outlined focus outline | 1px rest to 2px focus | PASS at baseline level |
| Supporting text | body-small | PASS at baseline level |
| Counter | body-small, trailing side | PASS at baseline level |
| Textarea | 96px min-height baseline | PASS with local baseline note |

Phase 2 must verify rendered values with Playwright. This Phase 1 audit does not claim final visual QA PASS.

---

## §4 — Container Dimensions

### §4.1 — Filled container

Baseline:

```css
.text-field {
  --_tf-h: 56px;
}

.text-field__container {
  min-height: var(--_tf-h);
}
```

Filled variant:

```txt
background-color: surface-container-highest
border-radius: extra-small-top
bottom active indicator via inset box-shadow
```

Required Phase 2 Playwright check:

```txt
filled .text-field__container height = 56px for single-line specimens
```

### §4.2 — Outlined container

Outlined variant:

```txt
background transparent
border-radius extra-small
inset outline box-shadow
```

Required Phase 2 Playwright check:

```txt
outlined .text-field__container height = 56px for single-line specimens
```

### §4.3 — Textarea container

Textarea:

```txt
container switches to block layout
textarea min-height = 96px
resize = vertical
```

Required Phase 2 Playwright check:

```txt
textarea rendered min-height >= 96px
label does not collide with first line
```

---

## §5 — Typography And Label Transition Measurements

Rest label:

```txt
body-large size
body-large line-height
vertically centered
```

Floated label:

```txt
body-small size
body-small line-height
top: 8px for filled/single-line
outlined notch sits over outline top edge
```

Input value:

```txt
body-large typography inherited from container
caret-color primary at default focus
error state overrides caret and text fill color
```

Phase 2 Playwright requirements:

```txt
trace label computed top/font-size before focus
trace label computed top/font-size after focus
trace populated field label position without focus
verify no label/prefix collision
```

---

## §6 — Active Indicator / Outline / State Colors

### §6.1 — Filled active indicator

Filled rest:

```txt
inset bottom 1px on-surface-variant
```

Filled hover:

```txt
container surface mix
bottom indicator on-surface
```

Filled focus:

```txt
inset bottom 2px primary
label primary
```

Filled error:

```txt
bottom indicator error
label/supporting/counter error
input value/caret error
```

### §6.2 — Outlined active indicator

Outlined rest:

```txt
inset outline 1px outline
```

Outlined hover:

```txt
outline on-surface
```

Outlined focus:

```txt
inset outline 2px primary
label primary
```

Outlined error:

```txt
outline error
focus error outline 2px
```

### §6.3 — State-layer distinction

Text field does not use animated ripple or the Button-style state-layer surface on the field host.

Measurement implication:

```txt
Do not measure ripple/state-layer overlay on .text-field__container.
Measure active indicator and outline behavior instead.
```

---

## §7 — Slot Geometry

### §7.1 — Leading icon

Leading icon:

```txt
grid-column: 1
grid-row: 1
icon size: var(--comp-icon-size-md)
```

With-leading label offset:

```txt
inset-inline-start = padding + icon-size + gap
```

Outlined floated label cancels leading offset for the notch.

### §7.2 — Prefix

Prefix:

```txt
grid-column: 2
grid-row: 1
hidden at rest when empty and not focused
```

### §7.3 — Input and label

Input:

```txt
grid-column: 3
grid-row: 1
min-width: 0
```

Label:

```txt
absolute position
starts at inline padding
floats based on focus/populated state
```

### §7.4 — Suffix

Suffix:

```txt
grid-column: 4
grid-row: 1
hidden at rest when empty and not focused
```

Input with suffix:

```txt
text-align: end
```

### §7.5 — Trailing icon and error icon

Trailing icon:

```txt
grid-column: 5
grid-row: 1
```

Interactive trailing icon-button:

```txt
40px Icon button geometry
negative inline/block margin = -8px
```

Error icon:

```txt
same grid cell
hidden by default
shown on error
trailing icon hidden on error
```

---

## §8 — Bottom Row: Supporting Text + Counter

Bottom row:

```txt
display flex
justify-content space-between
gap var(--space-md)
padding-inline var(--_tf-px)
margin-top var(--space-xs)
body-small typography
```

Supporting text:

```txt
leading side
on-surface-variant by default
error color during error states
```

Counter:

```txt
trailing side
white-space nowrap
margin-inline-start auto
static visual in v3.5.7
```

Phase 2 Playwright requirements:

```txt
helper and counter do not overlap
counter remains trailing
error state colors both supporting and counter
```

---

## §9 — Textarea Measurements

Textarea-specific baseline:

```txt
.text-field__container:has(textarea.text-field__input) {
  display: block;
  padding-block: calc(var(--space-md) + var(--_tf-label-rest-lh)) var(--space-md);
}

textarea.text-field__input {
  resize: vertical;
  min-height: 96px;
}
```

Measurement duties:

```txt
verify min-height
verify label rest position
verify label floated position
verify supporting/counter row still works
verify no prefix/suffix assumptions are applied to textarea
```

Textarea is a variant; it does not start a new module or matrix row.

---

## §10 — WCAG And Native Form-Control Applicability

### §10.0 — Specific SC coverage

Text field is an Inputs-family component, so WCAG coverage is not primarily a touch-target story. Phase 1 records the applicable Success Criteria explicitly for G9 accuracy:

| SC | Name | Text field applicability | Phase 1 disposition |
|---|---|---|---|
| 1.4.3 | Contrast (Minimum) (AA) | input value, label, supporting text, error text | Applies; Phase 2/3 must verify token contrast |
| 1.4.11 | Non-text Contrast (AA) | outline, active indicator, error indicator, focus affordance | Applies; active indicator/outline colors must remain visible |
| 1.3.5 | Identify Input Purpose (AA) | name/email/tel/url fields and autocomplete tokens | Applies to authoring guidance; plugin/editor may own schema |
| 3.3.1 | Error Identification (A) | `.is-error` and `:user-invalid` visual states | Applies; error state must identify invalid fields |
| 3.3.2 | Labels or Instructions (A) | real `.text-field__label` + helper text | Applies; placeholder-only label forbidden |
| 3.3.3 | Error Suggestion (AA) | supporting/error text can suggest correction | Applies when validation message exists; generation is plugin/editor territory |
| 4.1.3 | Status Messages (AA) | async validation / live counter updates | Integration territory; theme provides surface, plugin/editor wires `aria-live` if needed |
| 2.5.8 | Target Size (Minimum) (AA) | action targets | N/A to field host as text-entry surface; applies to composed clear icon-button |
| 2.5.5 | Target Size (Enhanced) (AAA) | action targets | N/A to field host as text-entry surface; applies to composed clear icon-button |

Target-size note:

```txt
The Text field host is a native text-entry surface, not an action button.
SC 2.5.8 / 2.5.5 are therefore not the primary measurement gates for the
field container. They remain relevant to the composed trailing clear
icon-button, which inherits Icon button's target-size contract.
```

### §10.1 — Text input target

Native input/textarea controls provide platform keyboard and editing semantics. WCAG target-size analysis must distinguish:

```txt
field container:
  native text entry target

trailing clear button:
  separate icon-button target
```

### §10.2 — Label and name

Required:

```txt
real <label for>
input/textarea id
```

Placeholder-only labels are not acceptable.

### §10.3 — Error/supporting text

Phase 2 snippets should add `aria-describedby` for semantically connected helper/error text.

Current measurement verdict:

```txt
Phase 1 documents the requirement.
Phase 2 pattern HTML must prove it in snippets.
```

### §10.4 — Clear button

Trailing clear button must inherit Icon button accessibility:

```txt
real button
type="button"
aria-label
Icon button geometry
```

Clear-click behavior is out of scope; accessible name is still in scope.

---

## §11 — Deviations / Deferrals

Deferrals:

```txt
live counter behavior
clear-click behavior
custom validation runtime
Date/Time picker behavior
Search bar behavior
Select/dropdown behavior
readonly-specific visual styling if no baseline selectors exist
```

Potential future cleanup:

```txt
inline SVG specimens in style-guide text-field section may be replaced by
icon-system / Material Symbols patterns in a separate public-specimen cleanup.
```

No deferral blocks Phase 2 planning.

---

## §12 — Verdict

Measurement Phase 1 verdict:

| Criterion | Phase 1 status | Notes |
|---|---|---|
| Baseline measurement inventory | PASS | 56px / 96px / slots / state indicators recorded |
| M3 measurement comparison | PASS at audit level | Final visual PASS deferred to Phase 3 |
| WCAG/native form accuracy | PASS at audit level | Requires Phase 2 snippets to prove associations |
| Playwright requirements | PASS | Checks specified for Phase 2/3 |
| Deviations/deferrals | PASS | Clear/counter/runtime boundaries explicit |

Phase 1 verdict:

```txt
PASS at Phase 1 level.
```

---

## §13 — Cross-References

Companions:

```txt
TEXT-FIELD-SPEC-AUDIT.md
TEXT-FIELD-WP-MAPPING.md
```

Cycle docs:

```txt
docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md
docs/v3.5.7/TEXT-FIELD-PHASE-1-PLAN.md
```
