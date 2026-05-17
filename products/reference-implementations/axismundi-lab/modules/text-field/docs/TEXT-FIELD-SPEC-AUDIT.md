# Text Field — Spec Audit (v3.5.7 Phase 1 / Phase 2 Bookkeeping)

> **Status**: Phase 2 pattern artifacts created; Phase 3 visual QA pending.  
> **Component**: Text field #16  
> **Category**: Component Full-Spec + Interaction  
> **Primary Phase 0 source**: `docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md`  
> **Execution plan**: `docs/v3.5.7/TEXT-FIELD-PHASE-1-PLAN.md`  
> **Companions**: `TEXT-FIELD-MEASUREMENT-AUDIT.md`, `TEXT-FIELD-WP-MAPPING.md`

---

## §0 — Audit Status

This audit converts the v3.5.7 Phase 0 Text field findings into the Component Full-Spec audit lane while also covering the Interaction gates called out by the matrix category.

Phase 1 was documentation-only. Phase 2 has since created the approved lab
pattern artifacts:

```txt
lab-text-field.css created.
lab-text-field-pattern.html created.
No lab-text-field.js (static clear/counter/validation contract).
No baseline or public-surface edits.
```

Phase 0 settled the critical dual-category decision:

```txt
Create a 3-doc Component Full-Spec trio.
Do NOT create TEXT-FIELD-RUNTIME-AUDIT.md in v3.5.7.
```

Reason:

```txt
Text field interaction is currently native/CSS state behavior, not an
extracted reusable JavaScript runtime. Interaction coverage belongs in this
SPEC audit and in WP-MAPPING.
```

---

## §1 — Inputs Read

Authoritative cycle docs:

```txt
docs/v3.5.7/TEXT-FIELD-PHASE-0-PLAN.md
docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md
docs/v3.5.7/TEXT-FIELD-PHASE-1-PLAN.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
```

Baseline/public sources:

```txt
components.css §9 Text field
style-guide.html #components-text-field
```

External references:

```txt
M3 Text fields specs:
  https://m3.material.io/components/text-fields/specs

M3 Text fields guidelines:
  https://m3.material.io/components/text-fields/guidelines

Material Web text field:
  https://material-web.dev/components/text-field/
```

Authority rule:

```txt
M3 specs/guidelines:
  design-system reference.

Material Web:
  secondary implementation comparison only. Do not import it, depend on it,
  or treat its custom elements as Axismundi runtime authority.
```

Wave 1 precedents:

```txt
Button v3.5.1       Pattern A disabled split, native control principle.
Icon button v3.5.2  accessible-name strictness for trailing clear action.
Card v3.5.3         semantics decision tree and anti-pattern inventory.
FAB v3.5.5          static behavior deferral and caption discipline.
Ripple v3.5.6       Playwright QA and class-name precision.
Chip v3.4.9         original Component Full-Spec audit trio.
```

---

## §2 — Baseline Inventory Summary

Current baseline location:

```txt
components.css §9 Text field
Approximate source range: lines 930-1474
```

The baseline includes:

```txt
wrapper / visual container / input / label split
filled and outlined variants
leading icon slot
trailing icon slot
error icon slot
prefix and suffix slots
supporting text and counter bottom row
textarea support
manual error state
native :user-invalid state
native disabled state
aria-disabled wrapper visual state
clear button visibility via .is-clear and :placeholder-shown
```

Key class-name contract:

```txt
.text-field
.text-field--filled
.text-field--outlined
.text-field--with-leading
.text-field__container
.text-field__input
.text-field__label
.text-field__leading-icon
.text-field__trailing-icon
.text-field__error-icon
.text-field__prefix
.text-field__suffix
.text-field__bottom
.text-field__supporting
.text-field__counter
.is-clear
```

Forbidden invented names:

```txt
.textfield
.textinput
.input-field
.text-field__body
.text-field__helper
.text-field__content
```

Baseline private measurements:

```txt
--_tf-h: 56px
--_tf-px: var(--space-md)
--_tf-label-rest-size: body-large
--_tf-label-float-size: body-small
textarea min-height: 96px
```

---

## §3 — M3 Text Field Spec Alignment

Text field maps to the M3 Text fields component surface:

```txt
filled text field
outlined text field
leading icon
trailing icon
prefix / suffix
supporting text
error state
counter
multiline textarea form
```

Current baseline alignment:

| M3 concept | Axismundi current surface | Status |
|---|---|---|
| Filled field | `.text-field.text-field--filled` | CURRENT |
| Outlined field | `.text-field.text-field--outlined` | CURRENT |
| Label | real `<label class="text-field__label" for>` | CURRENT |
| Input | native `<input class="text-field__input">` | CURRENT |
| Multiline | `textarea.text-field__input` | CURRENT |
| Leading icon | `.text-field__leading-icon` | CURRENT optional |
| Trailing action | composed `.ax-icon-button.text-field__trailing-icon` | CURRENT optional |
| Prefix/suffix | `.text-field__prefix` / `.text-field__suffix` | CURRENT optional |
| Supporting text | `.text-field__supporting` | CURRENT optional |
| Counter | `.text-field__counter` | CURRENT static |
| Error icon | `.text-field__error-icon` | CURRENT |
| Native validation | `:user-invalid` + attributes | CURRENT |

M3 / Axismundi implementation boundary:

```txt
M3 defines the visual and interaction semantics.
Axismundi implements those semantics through native HTML + CSS, not through
Material Web custom elements.
```

Material Web comparison:

```txt
Useful as implementation reference:
  filled / outlined split
  label and support text concepts
  native form-association concerns

Not an Axismundi dependency:
  <md-filled-text-field>
  <md-outlined-text-field>
  @material/web runtime
```

---

## §4 — Native Markup And Accessibility Contract

### §4.1 — Native control requirement

Text field must be built from native controls:

```html
<input class="text-field__input">
<textarea class="text-field__input"></textarea>
```

Forbidden:

```html
<div role="textbox" class="text-field__input"></div>
<span contenteditable class="text-field__input"></span>
```

Principle 1:

```txt
Visible control = real runtime control.
```

Principle 2:

```txt
Use the native semantic element when the platform already provides one.
```

### §4.2 — Label contract

Every text field must have a real label:

```html
<input class="text-field__input" id="tf-email" placeholder=" ">
<label class="text-field__label" for="tf-email">Email</label>
```

The label is both:

```txt
visual floating label
accessible name source
```

Placeholder-only labels are forbidden.

### §4.3 — Container is not a label

Current baseline deliberately uses:

```html
<div class="text-field__container">...</div>
```

not:

```html
<label class="text-field__container">...</label>
```

Reason:

```txt
Interactive trailing buttons can live inside the container. HTML label
descendant rules make button-in-label markup invalid/problematic when the
button is not the label's target.
```

### §4.4 — Supporting text association

Recommended Phase 2 snippet pattern:

```html
<input id="tf-title" aria-describedby="tf-title-help">
<span class="text-field__supporting" id="tf-title-help">Maximum 50 characters.</span>
```

The current public specimen does not consistently encode this association, but Phase 1 documents it as the audit recommendation for authored snippets.

### §4.5 — Trailing clear button accessible name

Clear buttons must be real buttons with accessible names:

```html
<button type="button"
        class="ax-icon-button is-standard has-state-layer text-field__trailing-icon is-clear"
        aria-label="Clear">
  ...
</button>
```

The clear button is a composed Icon button consumer. Text field owns grid placement; Icon button owns control semantics, touch target, state layer, and ripple behavior if opted in by that consumer.

---

## §5 — Variants: Filled / Outlined / Textarea

### §5.1 — Filled

Baseline:

```html
<div class="text-field text-field--filled">
  <div class="text-field__container">...</div>
</div>
```

Current behavior:

```txt
surface-container-highest fill
1px bottom active indicator at rest
2px primary active indicator at focus
floating label
native invalid/error override
disabled Pattern A visual treatment
```

### §5.2 — Outlined

Baseline:

```html
<div class="text-field text-field--outlined">
  <div class="text-field__container">...</div>
</div>
```

Current behavior:

```txt
transparent container
1px outline at rest
2px primary outline at focus
outline notch via floated label backdrop
manual/native error override
```

### §5.3 — Textarea

Baseline:

```html
<textarea class="text-field__input" id="tf-message" placeholder=" " rows="3"></textarea>
```

Decision:

```txt
Textarea is a Text field variant, not a separate matrix row.
```

Current behavior:

```txt
container switches from grid to block layout
textarea min-height 96px
resize: vertical
label top-positioned at rest and floats to 8px
supporting/counter bottom row remains compatible
```

---

## §6 — States: Rest / Hover / Focus / Populated / Error / Disabled / Readonly

### §6.1 — Rest

Rest state:

```txt
input empty
not focused
label at body-large size, vertically centered
prefix/suffix hidden
no native invalid state yet
```

The baseline uses `placeholder=" "` only as a CSS state hook for `:placeholder-shown`.

### §6.2 — Hover

Hover is visual only:

```txt
filled: subtle container surface mix
outlined: outline color strengthens
```

No JavaScript is required.

### §6.3 — Focus

Focus uses `:focus-within` on `.text-field__container`:

```txt
label floats
active indicator switches to primary
caret uses primary unless error overrides it
```

Text field replaces the generic global focus ring with its own active indicator on the container. This is a CURRENT baseline choice and must be documented in MEASUREMENT.

### §6.4 — Populated

Populated state:

```txt
.text-field__input:not(:placeholder-shown) ~ .text-field__label
```

The label floats without JS. Phase 2 pattern HTML must include populated specimens for both filled and outlined variants.

### §6.5 — Manual error

Manual error:

```html
<div class="text-field text-field--outlined is-error">...</div>
```

Manual error is a static state useful for examples and server-side rendered validation. It is in scope.

### §6.6 — Native error

Native error:

```css
.text-field:has(.text-field__input:user-invalid)
```

Attributes in scope:

```txt
required
type="email"
pattern
maxlength
```

Native `:user-invalid` is browser-driven and fires after user interaction. This is interaction surface, but not Axismundi JS runtime.

### §6.7 — Disabled

Primary disabled pattern:

```html
<input class="text-field__input" disabled>
```

This is Pattern A for native controls. It blocks editing through platform behavior.

### §6.8 — aria-disabled plugin-managed visual state

Wrapper visual state:

```html
<div class="text-field text-field--filled" aria-disabled="true">...</div>
```

This communicates state and applies visual treatment, but does not replace native disabled when a native control is present. Any activation/editing guard is plugin/editor/theme territory.

### §6.9 — Readonly

Phase 0 did not identify a rich baseline readonly styling system.

Phase 1 classification:

```txt
readonly is native HTML support.
If no readonly-specific baseline selectors are present, document it as native
support without claiming a distinct Axismundi visual state.
```

---

## §7 — Slots

### §7.1 — Leading icon

Leading icon slot:

```html
<span class="text-field__leading-icon">...</span>
```

Dependency:

```txt
icon-system/ = CURRENT conditional
```

Text field remains valid without a leading icon.

### §7.2 — Trailing icon / clear button

Trailing action slot:

```html
<button class="ax-icon-button is-standard has-state-layer text-field__trailing-icon is-clear"
        type="button"
        aria-label="Clear">
  ...
</button>
```

Decision:

```txt
Clear button behavior is static/visual only in v3.5.7.
```

The `.is-clear` modifier only controls CSS visibility when the input is empty. It does not clear text by itself.

### §7.3 — Error icon

Error icon slot:

```html
<span class="text-field__error-icon">...</span>
```

Current baseline:

```txt
hidden by default
shown by .is-error or :user-invalid
mutually exclusive with trailing icon in the same grid column
```

### §7.4 — Prefix and suffix

Prefix/suffix slots:

```html
<span class="text-field__prefix">$</span>
<span class="text-field__suffix">USD</span>
```

Current behavior:

```txt
hidden at rest when empty and unfocused
shown when label floats
input with suffix aligns text to inline-end
```

### §7.5 — Bottom row

Bottom row:

```html
<div class="text-field__bottom">
  <span class="text-field__supporting">Helper text</span>
  <span class="text-field__counter">0 / 50</span>
</div>
```

Counter is static in v3.5.7. Live updates are integration behavior.

---

## §8 — Interaction Boundary: Native/CSS vs Integration Runtime

### §8.1 — In scope: native/CSS interaction

In scope:

```txt
label float
focus-within active indicator
hover container/outline response
:placeholder-shown empty-state detection
:user-invalid native validation state
:disabled native disabled state
:has() parent-state styling
clear button visibility when input is empty
prefix/suffix visibility when label floats
textarea layout behavior
```

These are interaction surfaces and must be covered by G11-G16 readiness, but they do not require a runtime module.

### §8.2 — Out of scope: integration runtime

Out of scope:

```txt
clear button click handler
live character counter
custom validation messages
async validation
server validation
submission handling
field schema persistence
date/time picker behavior
dropdown/select behavior
search expansion behavior
```

These belong to theme/editor/plugin integration or separate component rows.

### §8.3 — Fourth audit doc decision

Decision:

```txt
No TEXT-FIELD-RUNTIME-AUDIT.md in v3.5.7.
```

Reopen only if a later cycle approves a reusable `lab-text-field.js` contract for clear/counter/validation behavior.

---

## §9 — Clear Button And Counter Static-Scope Decisions

### §9.1 — Clear button

Allowed:

```txt
.is-clear
accessible trailing button specimen
static caption
auto-hide when input is empty via CSS
```

Required future caption:

```txt
Static catalog specimen — clear affordance is visual only; no JS click
handler is wired in v3.5.7.
```

### §9.2 — Counter

Allowed:

```txt
maxlength
static "0 / 50" or "0 / 280"
data-tf-counter as metadata
```

Not allowed in v3.5.7 without separate approval:

```txt
live counter JS
counter event API
validation/counter runtime helper
```

---

## §10 — Textarea And Date/Time Boundary

### §10.1 — Textarea

Textarea is in scope:

```txt
textarea.text-field__input
floating label
supporting text
counter
disabled
error
```

Not in scope:

```txt
rich text editor
auto-grow runtime
markdown editor
```

### §10.2 — Date/Time

Text field owns:

```txt
generic input shell
label
slots
supporting/counter row
native validity display
```

Date picker and Time picker own:

```txt
calendar grid
clock/dial
modal/docked surfaces
popover/dialog behavior
date/time parsing and selection logic
```

Text field may be composed by Date/Time picker later, but it does not absorb those rows.

---

## §11 — Dependency Profile / Consumer-State

| Provider | Consumer state | Notes |
|---|---|---|
| `components.css §9` | CURRENT | Text field baseline primitive |
| `components.css §0` | CURRENT partial | Shared state-layer/focus policy exists; Text field uses its own active indicator |
| `icon-system/` | CURRENT conditional | Optional leading/trailing/error glyphs and composed icon-button |
| `ripple/` | NONE for field host | No `data-ax-ripple` on `.text-field__container` |
| `ripple/` via icon-button | TARGET through composed consumer only | Icon button owns the ripple contract |
| `popover/` | NONE for generic Text field | Date/Time/dropdown/select own popover behavior |
| `search-expansion/` | NONE | Search bar #17 owns search-specific expansion |

### §11.1 — icon-system nuance

Text field is not icon-defined:

```txt
Text field without icons remains a valid Text field.
```

Therefore:

```txt
icon-system/ = CURRENT conditional
```

### §11.2 — ripple nuance

Text field focus/error/active indicators are not animated ripple. Do not treat field host as a Ripple v2 consumer.

Correct:

```html
<button class="ax-icon-button text-field__trailing-icon" data-ax-ripple="unbounded">
```

Incorrect:

```html
<div class="text-field__container" data-ax-ripple>
```

---

## §12 — Visible Control Principle + Native Semantics

Text field applies the Wave 1 Principle 1 / Principle 2 discipline:

```txt
Principle 1:
  Visible control = real runtime control.

Principle 2:
  Native semantic element first.
```

Required:

```txt
<input>
<textarea>
<label for>
<button type="button"> for clear/trailing actions
```

Forbidden:

```txt
<div role="textbox">
contenteditable as text-field primitive
label-less input with placeholder only
button inside label container
aria-disabled as a substitute for native disabled
```

---

## §13 — G1-G16 Gate Readiness

### §13.1 — G1-G10 Component Full-Spec

| Gate | Phase 5 status | Notes |
|---|---|---|
| G1 validator | PASS | 1.000 / 1.000 / 1.000 / 1.000 PASS preserved |
| G2 baseline untouched | PASS | `components.css §9`, `style-guide.html`, `tokens.css`, `blocks.css`, and `theme.json` untouched |
| G3 publish surface | PASS / N/A | No public publish-surface mutation required for this lab module cycle |
| G4 module artifacts | PASS | `lab-text-field.css` + `lab-text-field-pattern.html`; no JS runtime |
| G5 CHANGELOG | PASS | v3.5.7 release entry added |
| G6 Static Visual QA | PASS | Playwright trace + user visual QA; Phase 3 corrections closed in-cycle |
| G7 Principle 1 | PASS | Real `input`, `textarea`, `label`, and `button` controls only |
| G8 Principle 2 | PASS | Native form semantics; no custom textbox or `div role="button"` |
| G9 WCAG/native accuracy | PASS | MEASUREMENT §10.0 records SC 1.4.3 / 1.4.11 / 1.3.5 / 3.3.1 / 3.3.2 / 3.3.3 / 4.1.3 / 2.5.8 / 2.5.5 applicability |
| G10 3-doc pattern | PASS | SPEC + MEASUREMENT + WP-MAPPING; no standalone runtime audit |

### §13.2 — G11-G16 Interaction

| Interaction gate area | Phase 5 status |
|---|---|
| G11 hard interaction rules | PASS — label transition, native validation, disabled/readonly, clear/counter boundaries, and slot semantics are recorded in SPEC |
| G12 hard rules verified in code | PASS — CSS-only behavior + Playwright checks cover dimensions, label transition, native `:user-invalid`, and composed icon-button geometry |
| G13 Phase 0 inventory accuracy | PASS — baseline §9 class-name inventory and style-guide specimens drove Phase 0 and Phase 2 pattern markup |
| G14 forbidden ancestor/runtime guard | N/A — no extracted JS runtime; native controls remain browser-owned |
| G15 reduced motion | PASS — no custom JS animation; CSS transitions remain tokenized / baseline-governed |
| G16 audit doc shape | PASS — dual category resolved as 3-doc trio with interaction coverage embedded in SPEC, not a fourth runtime audit |

Verdict:

```txt
G11-G16 are applicable and PASS through SPEC/WP-MAPPING coverage, native
browser semantics, CSS-only state behavior, and Playwright verification.
No standalone runtime audit doc is required for v3.5.7 because Text field
does not extract a reusable JS runtime.
```

---

## §14 — Phase 2 Pattern Coverage

Phase 2 pattern HTML includes:

```txt
filled: rest / populated / focused / error / disabled
outlined: rest / populated / focused / error / disabled
prefix/suffix
counter
native validation specimen
leading icon
trailing clear icon
textarea
aria-disabled plugin-managed specimen
readonly if baseline-supported
```

Phase 2 / Phase 3 Playwright checks:

```txt
.text-field__container height = 56px for single-line variants
textarea min-height >= 96px
label floats on focus/populated
prefix/suffix visibility changes correctly
clear button hides when empty
error icon replaces trailing icon
bottom row helper/counter does not overlap
```

No v3.5.7 implementation adds `data-ax-ripple` to `.text-field__container`.

---

## §15 — Verdict

Phase 5 release verdict criteria:

| Criterion | Phase 5 status | Notes |
|---|---|---|
| #1 M3 Text field spec coverage | PASS | Filled + outlined variants, textarea variant, native input types, states, slots, supporting text, counter, clear affordance, and error/validation states covered |
| #2 Token-driven implementation / measurements | PASS | Phase 2 pattern consumes baseline §9 tokens and MEASUREMENT dimensions without mutating tokens.css/components.css |
| #3 Pattern HTML completeness | PASS | `lab-text-field-pattern.html` created with filled, outlined, states, slots, textarea, native input types, static clear/counter, and QA targets |
| #4 Audit doc completeness | PASS | SPEC/MEASUREMENT/WP-MAPPING authored, cross-referenced, and revised for WCAG SC specificity |
| #5 Dependency declarations | PASS | Explicit consumer-state table in §11; ripple field host NONE; composed icon-button/ripple via consumer; icon-system CURRENT conditional |
| #6 Interaction boundary / G11-G16 readiness | PASS | Interaction coverage embedded in SPEC; no runtime audit or JS module required |

Phase 5 verdict:

```txt
ALL PASS.

v3.5.7 Text field #16 is release-closed as the first Inputs family entry
and the first dual-category Component Full-Spec + Interaction closure.
```

---

## §16 — Cross-References

Companion docs:

```txt
TEXT-FIELD-MEASUREMENT-AUDIT.md
TEXT-FIELD-WP-MAPPING.md
```

Cycle docs:

```txt
docs/v3.5.7/TEXT-FIELD-PHASE-0-PLAN.md
docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md
docs/v3.5.7/TEXT-FIELD-PHASE-1-PLAN.md
```

Precedents:

```txt
Button v3.5.1
Icon button v3.5.2
Card v3.5.3
FAB v3.5.5
Ripple v3.5.6
Chip v3.4.9
```

---

## §17 — What This Audit Does NOT Do

This audit does not:

```txt
- Create lab-text-field.js.
- Implement clear-click behavior.
- Implement live counter behavior.
- Implement custom validation.
- Implement Date picker or Time picker.
- Implement Search bar.
- Implement dropdown/select/popover behavior.
- Import Material Web.
- Use <md-filled-text-field> or <md-outlined-text-field>.
- Add ripple to .text-field__container.
- Edit components.css §9.
- Edit style-guide.html #components-text-field.
- Edit tokens.css.
- Edit blocks.css.
- Edit theme.json.
- Edit CURRENT-STATE.md.
- Edit NEXT-SESSION.md.
```
