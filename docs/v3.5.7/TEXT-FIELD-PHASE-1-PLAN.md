# Axismundi v3.5.7 — Text Field #16 Phase 1 Plan

> **Status**: PLAN-ONLY v1.0. Awaiting review/approval before Phase 1 execution.  
> **Date**: 2026-05-16  
> **Preceded by**: `docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md`  
> **Scope**: Plan the Phase 1 audit-doc authoring pass for Text field #16.  
> **Non-scope**: No audit docs are created by this plan; no `text-field/` module directory, baseline files, state files, release files, or handoff files are edited.

---

## §0 — Goal

Phase 1 will convert the approved Phase 0 Text field findings into the standard Component Full-Spec audit body, with Interaction coverage embedded in SPEC and WP-MAPPING.

Planned deliverables after approval:

```txt
products/reference-implementations/axismundi-lab/modules/text-field/docs/TEXT-FIELD-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/text-field/docs/TEXT-FIELD-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/text-field/docs/TEXT-FIELD-WP-MAPPING.md
```

Phase 1 is documentation authoring only. It does not create:

```txt
lab-text-field.css
lab-text-field-pattern.html
lab-text-field.js
```

Text field is the first Wave 1 component with the dual matrix category:

```txt
Component Full-Spec + Interaction
```

Therefore Phase 1 must document:

```txt
G1-G10:
  Component Full-Spec audit readiness

G11-G16:
  Interaction readiness, but without extracting a runtime module
```

---

## §1 — Phase 1 Lock Decisions

This plan locks Text-field-specific decisions so the Phase 1 writer does not rediscover ontology boundaries during audit authoring.

### §1.1 — Audit Doc Shape

Decision:

```txt
Create the standard 3-doc Component Full-Spec trio.
Do NOT create TEXT-FIELD-RUNTIME-AUDIT.md in v3.5.7 Phase 1.
```

Reason:

```txt
Phase 0 found native/CSS interaction behavior, not an extracted reusable
runtime API. Label float, focus-within, :placeholder-shown, :user-invalid,
:disabled, and :has() belong in SPEC. Clear-click and live counter behavior
remain integration territory unless separately approved.
```

Execution implication:

```txt
Create modules/text-field/docs/ only after this plan is approved.
Do not create a fourth interaction doc.
Do not create lab-text-field.js.
```

### §1.2 — Dual Category Handling

Decision:

```txt
TEXT-FIELD-SPEC-AUDIT.md owns the interaction boundary.
TEXT-FIELD-WP-MAPPING.md owns the form integration boundary.
TEXT-FIELD-MEASUREMENT-AUDIT.md owns dimensions, tokens, typography,
state colors, and WCAG/target-size applicability.
```

SPEC must include:

```txt
- Component surface coverage
- Native markup contract
- Interaction boundary map
- G11-G16 applicability
- Runtime-extraction non-decision
```

WP-MAPPING must include:

```txt
- core/post-comments-form inventory
- core/search sibling boundary
- theme-can / plugin-should boundary
- form schema / validation / persistence anti-patterns
```

### §1.3 — Disabled Split

Decision:

```txt
Text field uses Pattern A native-control disabled split.
```

Phase 1 must split:

```txt
§5  Disabled — native disabled
    <input class="text-field__input" disabled>
    <textarea class="text-field__input" disabled>

§5a Disabled — aria-disabled plugin-managed visual state
    <div class="text-field" aria-disabled="true"> ... </div>
```

Native disabled:

```txt
Platform blocks field activation and editing.
```

aria-disabled:

```txt
Communicates visual/semantic state only.
Integrator/plugin must block editing/activation if the control remains focusable.
```

Card's Pattern B three-way split does not apply as the primary model. Text field is a native form-control wrapper, not a container card.

### §1.4 — Clear Button Scope

Decision:

```txt
Clear button is static/visual only in v3.5.7.
```

Phase 1 must document:

```txt
Allowed:
  .is-clear modifier
  auto-hide when input is empty via CSS
  composed ax-icon-button trailing action
  accessible name requirement

Not in scope:
  click handler that clears input
  lab-text-field.js
  public clear event API
```

Required caption language for future pattern specimens:

```txt
Static catalog specimen — clear affordance is visual only; no JS click
handler is wired in v3.5.7. Production usage wires the clear action at the
theme, block editor, or plugin level.

정적 카탈로그 specimen — clear affordance는 시각 시연만 하며 v3.5.7에서는
JS click handler를 배선하지 않는다. 실제 사용 시 theme / block editor /
plugin 레벨에서 clear 동작을 배선한다.
```

### §1.5 — Counter Scope

Decision:

```txt
Counter is static visual display in v3.5.7.
```

Phase 1 must document:

```txt
Allowed:
  .text-field__counter
  maxlength
  data-tf-counter as specimen metadata
  static "0 / 50" or "0 / 280" display

Not in scope:
  live character counting
  JS synchronization
  validation/counter runtime helper
```

### §1.6 — Native Validation Scope

Decision:

```txt
Native browser validation and CSS state mapping are in scope.
Custom validation generation is plugin/theme/editor territory.
```

In scope:

```txt
.is-error
:user-invalid
type="email"
pattern
maxlength
required
disabled
```

Out of scope:

```txt
async validation
server validation
schema persistence
custom message generation
field library runtime
```

Phase 1 must explicitly compare:

```txt
Manual error:
  .text-field.is-error

Native error:
  .text-field:has(.text-field__input:user-invalid)
```

### §1.7 — Textarea Scope

Decision:

```txt
Textarea is a Text field variant.
```

Phase 1 must not create a separate Textarea audit path or matrix row.

SPEC must include:

```txt
textarea.text-field__input
textarea label layout
min-height 96px
resize behavior
counter/supporting text compatibility
```

### §1.8 — Date/Time Boundary

Decision:

```txt
Text field owns generic input shell only.
Date picker #22 and Time picker #23 own picker behavior.
```

Text field owns:

```txt
input shell
label
slots
supporting/counter row
native validity display
```

Date/Time owns:

```txt
calendar grid
time dial
docked/modal surfaces
popover/dialog behavior
date/time parsing and selection logic
```

### §1.9 — Dependency Profile

Phase 1 must preserve this Phase 0 classification:

| Provider | Consumer state | Required wording |
|---|---|---|
| `components.css §9` | CURRENT | Text field baseline primitive |
| `components.css §0` | CURRENT partial | Shared state policy exists, but Text field uses its own active indicator |
| `icon-system/` | CURRENT conditional | Optional slots and composed trailing icon-button |
| `ripple/` | NONE for field host | Do not put `data-ax-ripple` on `.text-field__container` |
| `ripple/` via icon-button | TARGET only through composed consumer | icon-button owns its own ripple behavior |
| `popover/` | NONE for generic Text field | Date/Time/dropdown/select own popover behavior |
| `search-expansion/` | NONE | Search bar #17 owns search-specific behavior |

---

## §2 — Planned Audit Doc Structures

### §2.1 — TEXT-FIELD-SPEC-AUDIT.md

Planned sections:

```txt
§0  Status / scope
§1  Inputs read
§2  Baseline inventory summary
§3  M3 Text field spec alignment
§4  Native markup and accessible-name contract
§5  Variants: filled / outlined / textarea
§6  States: rest / hover / focus / populated / error / disabled / readonly
§7  Slots: leading / trailing / error / prefix / suffix / bottom row
§8  Interaction boundary: native/CSS vs integration runtime
§9  Clear button and counter static-scope decisions
§10 Textarea and Date/Time boundary
§11 Dependency profile / consumer-state
§12 Visible control principle + native semantics
§13 G1-G16 gate readiness
§14 Phase 2 pattern requirements
§15 Verdict
§16 Cross-references
§17 What this audit does NOT do
```

Notes:

```txt
- §8 is the dual-category anchor.
- §13 must cover G11-G16 without inventing a runtime doc.
- §17 should mirror Button/Card/FAB "does NOT do" discipline.
```

### §2.2 — TEXT-FIELD-MEASUREMENT-AUDIT.md

Planned sections:

```txt
§0  Status / scope
§1  Inputs read
§2  Baseline measurement inventory
§3  M3 measurement comparison
§4  Container dimensions
§5  Typography and label transition measurements
§6  Active indicator / outline / state colors
§7  Slot geometry: leading / trailing / prefix / suffix / error
§8  Bottom row: supporting text + counter
§9  Textarea measurements
§10 WCAG and native form-control applicability
§11 Deviations / deferrals
§12 Verdict
```

Required measurements:

```txt
Single-line container:
  min-height 56px

Textarea:
  min-height 96px

Label:
  rest body-large
  floated body-small

Outlined:
  rest outline 1px
  focus outline 2px

Filled:
  rest bottom indicator 1px
  focus bottom indicator 2px

Interactive trailing icon:
  composed ax-icon-button geometry, not text-field-owned button sizing
```

WCAG nuance:

```txt
Text field host is a native input/textarea target.
Trailing clear button is a separate Icon button consumer and must preserve
Icon button target/accessibility rules.
```

### §2.3 — TEXT-FIELD-WP-MAPPING.md

Planned sections:

```txt
§0  Status / scope
§1  Inputs read
§2  WordPress surface inventory
§3  core/post-comments-form mapping
§4  core/search sibling boundary
§5  core/html / pattern composition path
§6  Theme-can / plugin-should boundary
§7  Field schema / validation / persistence boundary
§8  Date/Time and Search bar composition boundary
§9  Accessible form contract
§10 Anti-pattern inventory
§11 ActivityPub/social CMS notes, if any
§12 Verdict
§13 Cross-references
```

Required anti-patterns:

```txt
- Placeholder-only label.
- .text-field__container as <label> containing buttons.
- <div role="textbox"> instead of input/textarea.
- Clear button without accessible name.
- Counter without maxlength/source of truth.
- Custom validation hidden inside theme CSS.
- Date picker/calendar folded into generic Text field.
- Search-specific expansion folded into generic Text field.
- Inline SVG icon snippets where icon-system pattern should be used.
- Directly styling arbitrary core input markup while bypassing .text-field wrapper contract.
```

---

## §3 — Inputs Phase 1 Execution Must Read

### §3.1 — Canonical sources

Execution must read:

```txt
docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md
docs/v3.5.7/TEXT-FIELD-PHASE-0-PLAN.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
```

### §3.2 — Baseline files

Execution must inspect:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
  §9 Text field

products/reference-implementations/axismundi-lab/style-guide.html
  #components-text-field
```

The plan requires a selector verification pass before audit authoring:

```txt
.text-field
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
.text-field--filled
.text-field--outlined
.text-field--with-leading
.is-clear
```

### §3.3 — External references

Execution should cite:

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
  design reference

Material Web:
  stale/secondary implementation comparison only
```

Do not import Material Web and do not treat `<md-filled-text-field>` or `<md-outlined-text-field>` as an Axismundi dependency.

### §3.4 — Precedent audits

Use:

```txt
Button v3.5.1:
  disabled split, native control principle, 3-doc shape

Icon button v3.5.2:
  accessible-name strictness for trailing clear button

Card v3.5.3:
  native semantics decision tree, anti-pattern inventory

FAB v3.5.5:
  static behavior deferral, caption discipline

Ripple v3.5.6:
  Playwright QA, class-name precision, dependency alignment notes

Chip v3.4.9:
  original 3-doc template
```

---

## §4 — Class-Name Precision Rule

Phase 1 must make class-name precision a first-class audit requirement.

Reason:

```txt
Text field slot behavior depends on exact baseline selectors and DOM order.
The v3.5.6 Tab QA finding proved that plausible names can silently miss
baseline behavior.
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

Phase 1 SPEC and future Phase 2 plan must require:

```txt
Before authoring pattern HTML:
  grep components.css §9 and style-guide.html #components-text-field

During authoring:
  copy class names exactly

After authoring:
  run Playwright selector and dimension checks
```

---

## §5 — Disabled / Readonly / Required Split

### §5.1 — Native disabled

Required audit position:

```txt
Native disabled is the primary disabled implementation for input and textarea.
```

Specimen shape:

```html
<input class="text-field__input" id="tf-disabled" placeholder=" " disabled>
<label class="text-field__label" for="tf-disabled">Disabled</label>
```

### §5.2 — aria-disabled plugin-managed state

Required audit position:

```txt
aria-disabled on wrapper is visual/plugin-managed state.
It does not replace native disabled for native controls.
```

Specimen caption pattern:

```txt
Plugin-managed specimen — aria-disabled communicates state, but the integrator
must guard editing, events, and submission.

plugin-managed specimen — aria-disabled는 상태를 전달하지만 편집, 이벤트,
submission 차단은 통합 코드가 담당한다.
```

### §5.3 — readonly

Phase 0 did not confirm a rich readonly baseline specimen. Phase 1 must inspect whether `components.css §9` has any readonly-specific rules.

Decision for Phase 1:

```txt
If readonly-specific baseline exists:
  document it as CURRENT.

If not:
  record readonly as native HTML support but not a styled Axismundi state.
```

### §5.4 — required

Required is in scope as a native validation attribute:

```txt
required
:user-invalid after user interaction
manual .is-error fallback for static specimens
```

Do not create custom required validation runtime.

---

## §6 — Variant / State / Slot Matrix

Phase 1 must define the matrix that Phase 2 pattern HTML will later implement.

### §6.1 — Variants

Required:

```txt
Filled
Outlined
Textarea as variant
With leading icon
With trailing clear icon
With prefix/suffix
With bottom row helper/counter
```

Do not invent:

```txt
extra density variants
new size variants
separate Textarea component
Search bar variant
Date/Time picker variant
Select/dropdown variant
```

### §6.2 — States

Required:

```txt
Rest
Hover
Focused
Populated
Manual error (.is-error)
Native error (:user-invalid)
Disabled
Readonly if baseline-supported
Required via native validation
```

### §6.3 — Slots

Required:

```txt
Leading icon
Trailing icon-button
Error icon
Prefix
Suffix
Supporting text
Counter
```

Slot ownership:

```txt
Text field owns placement.
icon-system owns glyph rendering.
icon-button owns interactive trailing action behavior.
```

### §6.4 — Native input types

Phase 1 should inventory likely safe input types:

```txt
text
email
url
tel
password
number / numeric inputmode
search only as generic input type, not Search bar #17 behavior
date / time only as native input shell, not Date/Time picker behavior
```

Do not overpromise browser-native date/time styling parity.

---

## §7 — Playwright QA Requirements for Later Phases

Phase 1 must specify Playwright requirements that Phase 2/3 will execute.

### §7.1 — Dimension checks

Required Phase 2 checks:

```txt
filled .text-field__container height = 56px
outlined .text-field__container height = 56px
textarea min-height >= 96px
trailing icon-button remains 40px control within slot
bottom row helper/counter aligns without overlap
```

### §7.2 — State checks

Required Phase 2/3 checks:

```txt
focus-within label floats
populated label floats
manual .is-error colors label/supporting/counter/error icon
:user-invalid native state is testable where browser supports it
disabled opacity/color treatment
clear button hidden when input is empty
prefix/suffix hidden at rest and visible after focus/populated
```

### §7.3 — Screenshot artifacts

QA screenshots may be written under:

```txt
docs/v3.5.7/*-qa.png
docs/v3.5.7/qa-*.png
```

These are gitignored and are not release artifacts.

---

## §8 — WordPress / Form Mapping Locks

Phase 1 WP-MAPPING must formalize the Phase 0 stub.

### §8.1 — Mapping surfaces

Required inventory:

```txt
core/post-comments-form
core/search sibling boundary
core/html static composition path
theme pattern composition
plugin/editor custom field path
```

### §8.2 — Theme-can / plugin-should

Theme can:

```txt
style native controls
ship static field patterns
map component classes into templates
provide visual states
```

Plugin/editor should:

```txt
own schema
own validation logic
own submissions
own persistence
own dynamic counter
own clear-click behavior when tied to data
```

### §8.3 — Anti-pattern inventory

Required anti-patterns:

```txt
placeholder-only label
.text-field__container as <label> containing buttons
<div role="textbox">
clear button without accessible name
counter without maxlength/source of truth
custom validation hidden in theme CSS
Date/Time picker behavior folded into Text field
Search bar expansion folded into Text field
inline SVG where icon-system pattern should be used
arbitrary core input styling bypassing .text-field wrapper contract
```

---

## §9 — SPEC Verdict Shape

Phase 1 should establish a verdict framework that can close in Phase 5.

Recommended SPEC verdict criteria:

```txt
#1 M3 Text field spec coverage
#2 Token-driven implementation and measurement coverage
#3 Pattern HTML completeness
#4 Audit doc completeness
#5 Dependency declarations
#6 Interaction boundary / G11-G16 readiness
```

Phase 1 expected result:

```txt
PASS at Phase 1 level:
  #4 Audit doc completeness
  #5 Dependency declarations
  #6 Interaction boundary / G11-G16 readiness, if fully documented

Deferred to Phase 5:
  #1 M3 spec coverage
  #2 Token-driven implementation
  #3 Pattern HTML completeness
```

Reason:

```txt
Text field is dual category, so a sixth criterion for Interaction boundary is
clearer than hiding G11-G16 inside generic spec coverage.
```

---

## §10 — Phase 1 Execution File Scope

After approval, Phase 1 execution may create:

```txt
products/reference-implementations/axismundi-lab/modules/text-field/docs/
products/reference-implementations/axismundi-lab/modules/text-field/docs/TEXT-FIELD-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/text-field/docs/TEXT-FIELD-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/text-field/docs/TEXT-FIELD-WP-MAPPING.md
```

Phase 1 execution must not create:

```txt
products/reference-implementations/axismundi-lab/modules/text-field/lab-text-field.css
products/reference-implementations/axismundi-lab/modules/text-field/lab-text-field-pattern.html
products/reference-implementations/axismundi-lab/modules/text-field/lab-text-field.js
```

Phase 1 execution must not edit:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
theme.json
CURRENT-STATE.md
NEXT-SESSION.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md
```

---

## §11 — Non-goals

Not in Phase 1 plan or execution:

```txt
- Implement Text field module CSS.
- Implement Text field pattern HTML.
- Implement lab-text-field.js.
- Implement clear-click behavior.
- Implement live counter behavior.
- Implement custom validation.
- Implement Date picker.
- Implement Time picker.
- Implement Search bar.
- Implement dropdown/select/popup behavior.
- Import Material Web.
- Add <md-filled-text-field> or <md-outlined-text-field>.
- Add ripple to .text-field__container.
- Edit baseline components.css §9.
- Edit style-guide.html #components-text-field.
- Edit tokens.css.
- Edit blocks.css.
- Edit theme.json.
- Edit release docs or state/handoff docs.
```

---

## §12 — Risks

| Risk | Description | Plan mitigation |
|---|---|---|
| R1 | Dual category causes over-shaped runtime doc | Lock 3-doc trio; G11-G16 inside SPEC |
| R2 | Interaction content too thin | Add explicit SPEC §8 + §13 interaction coverage |
| R3 | Clear/counter behavior creeps into JS | Static-only locks in §1.4 and §1.5 |
| R4 | DOM/class drift breaks selectors | Class-name precision rule in §4 |
| R5 | Disabled/aria-disabled collapse | Pattern A split in §5 |
| R6 | Textarea split into separate component | Lock as variant in §1.7 |
| R7 | Date/Time picker absorbed | Boundary lock in §1.8 |
| R8 | WP-MAPPING overclaims plugin work | Theme-can/plugin-should split in §8 |
| R9 | icon-system dependency over-promoted | CURRENT conditional only in §1.9 |
| R10 | ripple applied to field host | NONE for host in §1.9 |
| R11 | Material Web treated as authority | Secondary stale reference only in §3.3 |
| R12 | Playwright checks deferred too late | Phase 2/3 requirements in §7 |

---

## §13 — Validation Plan

Plan-stage validation:

```txt
- Confirm this plan creates only docs/v3.5.7/TEXT-FIELD-PHASE-1-PLAN.md.
- Confirm lab/modules/text-field/ still does not exist.
- Confirm baseline/state/handoff files are untouched.
- Run validator and preserve 1.000 / 1.000 / 1.000 / 1.000 PASS.
```

Phase 1 execution validation after approval:

```txt
- Confirm 3 audit docs exist.
- Confirm no lab-text-field.css/html/js exist.
- Confirm no [NEXT SESSION:] markers remain.
- Confirm SPEC includes G11-G16 interaction coverage.
- Confirm dependency profile says ripple NONE for field host and icon-system CURRENT conditional.
- Confirm validator PASS.
```

---

## §14 — Approval Gate

This plan does not authorize audit doc creation by itself.

Approval flow:

```txt
Plan v1.0
  -> review
  -> optional v1.1 revision
  -> approval
  -> Phase 1 execution creates the 3 audit docs
```

No baseline, state, release, handoff, or implementation files are edited before approval.

---

## §15 — One-line summary

```txt
Text field Phase 1 should create a 3-doc Component Full-Spec audit trio,
embed G11-G16 interaction coverage inside SPEC/WP-MAPPING, keep clear/counter
static, preserve native form semantics, and defer all runtime behavior to
integration or a later explicitly-approved cycle.
```

