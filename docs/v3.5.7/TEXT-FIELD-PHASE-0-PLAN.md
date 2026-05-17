# v3.5.7 — Text Field #16 — Phase 0 Plan

Status: PLAN v1.0  
Scope: documentation-only planning for Phase 0 report  
Date: 2026-05-16  
Target matrix row: #16 Text field  
Category: Component Full-Spec + Interaction  

---

## §0 — Purpose

Plan the v3.5.7 Text field Phase 0 report before any audit docs or module artifacts are authored.

Text field is the first Wave 1 Inputs-family entry after Ripple v2 foundation cleanup. It is also the first Wave 1 row with the dual category:

```txt
Component Full-Spec + Interaction
```

That means Phase 0 must classify both:

```txt
Component surface:
  variants, slots, measurements, states, native markup, WordPress mapping

Interaction surface:
  label transition, native validation, clear button visibility, focus/error
  behavior, textarea behavior, form integration boundaries
```

This plan does not create `lab/modules/text-field/` and does not edit baseline files.

---

## §1 — Scope Locks

### §1.1 — Row identity

```txt
Matrix row:
  #16 Text field

TOC group:
  Inputs

Category:
  Component Full-Spec + Interaction

Status before v3.5.7:
  TODO

Target module:
  lab/modules/text-field/
```

Text field is not folded into Search bar. Matrix row #17 Search bar remains a sibling component with search-specific affordances and existing `search-expansion/` PARTIAL state.

### §1.2 — Baseline authority

Phase 0 must treat the following as canonical current inputs:

```txt
components.css §9 Text field:
  lines ~930-1474

style-guide.html #components-text-field:
  lines ~2342-2551
```

Baseline class names must be copied exactly. No invented aliases.

Required class-name precision checks:

```txt
Wrapper:
  .text-field
  .text-field--filled
  .text-field--outlined
  .text-field--with-leading

Container:
  .text-field__container

Input:
  .text-field__input

Label:
  .text-field__label

Slots:
  .text-field__leading-icon
  .text-field__trailing-icon
  .text-field__error-icon
  .text-field__prefix
  .text-field__suffix

Bottom row:
  .text-field__bottom
  .text-field__supporting
  .text-field__counter

Trailing button modifier:
  .is-clear
```

Forbidden:

```txt
.textfield
.textinput
.input-field
.text-field__body
.text-field__helper
.text-field__content
```

### §1.3 — Baseline mutation boundary

Phase 0 is read-only:

```txt
Do not edit:
  components.css
  style-guide.html
  tokens.css
  blocks.css
  theme.json
  CURRENT-STATE.md
  NEXT-SESSION.md
  CHANGELOG.md
  ROADMAP.md
  BACKLOG.md
```

Phase 0 may create only:

```txt
docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md
```

after this plan is approved.

### §1.4 — Module creation boundary

Do not create during Phase 0:

```txt
products/reference-implementations/axismundi-lab/modules/text-field/
```

The module directory belongs to Phase 1 or later, depending on the approved Phase 1 plan.

### §1.5 — Ripple state

Text field is not a ripple consumer by default.

Phase 0 should classify:

```txt
ripple/:
  NONE for text field input container

Reason:
  Text field focus/error/pressed affordances are handled by active indicator,
  outline, label transition, and native input state. Animated click ripple is
  not part of the current Text field baseline contract.
```

Interactive trailing icon buttons inside text field are separate composed consumers:

```txt
text-field/ owns:
  slot placement and field layout

icon-button/ owns:
  the actual trailing action button

ripple/ applies to:
  the composed icon-button if that icon-button opts in, not the field itself
```

### §1.6 — icon-system dependency

Text field has icon-system dependency only through optional slots.

Expected classification:

```txt
icon-system/:
  CURRENT conditional

Consumers:
  leading icon
  trailing icon
  error icon
  composed trailing icon-button
```

Unlike Icon button and FAB, Text field is not icon-defined. The dependency is slot-driven, not unconditional.

### §1.7 — Interaction classification

Text field is not an independent JavaScript runtime module by default.

Phase 0 must distinguish:

```txt
CSS/native interaction:
  label float
  focus-within active indicator
  :placeholder-shown
  :user-invalid
  :disabled
  :has()
  clear-button visibility when input is empty

Optional integration behavior:
  dynamic counter update
  clear button click handler
  voice/dropdown/date-picker trailing action
  form submission handling
  validation message generation
```

The first group may be Component Full-Spec + Interaction surface. The second group likely belongs to theme/editor/plugin integration and must be explicitly bounded.

---

## §2 — Required Inputs To Read

### §2.1 — Canonical framework docs

Phase 0 report must read or reference:

```txt
CONSTITUTION.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

### §2.2 — Baseline files

Phase 0 report must inspect:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
  §9 Text field

products/reference-implementations/axismundi-lab/style-guide.html
  #components-text-field
```

Minimum inventory:

```txt
components.css:
  rule block count
  selectors grouped by wrapper/container/input/label/slots/bottom/states
  private tokens such as --_tf-h and --_tf-px
  use of :has(), :placeholder-shown, :user-invalid, :disabled
  filled vs outlined selectors
  textarea selectors

style-guide.html:
  specimen count
  filled states
  outlined states
  prefix/suffix/counter specimens
  leading/trailing icon specimens
  textarea specimens
  ids / labels / native input attributes
```

### §2.3 — Reference precedents

Use these in order:

```txt
1. Button v3.5.1
   variant/state audit pattern; Pattern A disabled split; native control
   principle.

2. Icon button v3.5.2
   icon-system CURRENT unconditional contrast; accessible name strictness;
   runtime audit disposition precedent.

3. Card v3.5.3
   native semantics decision tree; composition slot handling; Pattern B
   disabled contrast.

4. FAB v3.5.5
   first family merge; icon-system unconditional contrast; static behavior
   deferral discipline.

5. Ripple v3.5.6
   Playwright-assisted visual QA and baseline class-name precision precedent.

6. Chip v3.4.9
   original 3-doc Component Full-Spec audit template.
```

### §2.4 — External M3 spec

Phase 0 should verify current M3 Text field guidance before report execution.

Expected source:

```txt
Material Design 3 Text fields guidance/spec
```

Do not rely only on memory. Text field is high-complexity and current spec details may matter.

---

## §3 — Phase 0 Report Shape

Create:

```txt
docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md
```

Required sections:

```txt
§0  Phase 0 framing
§1  Authoritative inputs
§2  Baseline §9 inventory
§3  Public specimen inventory
§4  Category classification: Component Full-Spec + Interaction
§5  Native markup + accessibility contract
§6  Variant/state/slot coverage
§7  Dependency profile / consumer-state
§8  Interaction boundary map
§9  WordPress/form mapping stub
§10 Playwright + baseline class-name QA plan
§11 Risks + dispositions
§12 Phase 1 entry conditions
§13 G1-G16 applicability
§14 Non-goals
§15 Verdict
```

Unlike Button/Icon button/Card/FAB, Text field Phase 0 must explicitly cover G11-G16 applicability because the matrix category includes Interaction.

---

## §4 — Phase 0 Questions To Settle

### §4.1 — Audit doc shape

Phase 1 likely follows a 3-doc Component Full-Spec trio plus an interaction section inside SPEC:

```txt
TEXT-FIELD-SPEC-AUDIT.md
TEXT-FIELD-MEASUREMENT-AUDIT.md
TEXT-FIELD-WP-MAPPING.md
```

Open decision for Phase 0:

```txt
Is a fourth interaction audit doc needed?
```

Recommendation:

```txt
No fourth doc in v3.5.7.

Reason:
  Text field interaction is mostly native/CSS state behavior, not an
  extracted runtime module. Put interaction boundary and G11-G16 readiness
  into SPEC and WP-MAPPING. Create a fourth doc only if Phase 0 discovers a
  real runtime API surface.
```

### §4.2 — Clear button scope

The baseline includes an `.is-clear` trailing icon button pattern that auto-hides when input is empty via CSS.

Phase 0 must settle:

```txt
v3.5.7 scope:
  visual/static clear affordance only
  OR clear-click JS behavior
```

Recommendation:

```txt
visual/static only
```

The click handler that clears input text is integration behavior. It can be demonstrated as a static specimen but should not force `lab-text-field.js` in Phase 2 unless explicitly approved.

### §4.3 — Counter scope

Baseline includes `.text-field__counter` and style-guide specimens with `data-tf-counter`.

Phase 0 must settle:

```txt
counter display:
  static visual specimen

counter update behavior:
  integration/plugin/theme JS, unless separately approved
```

Recommendation:

```txt
static counter only in v3.5.7
```

### §4.4 — Native validation scope

Baseline supports:

```txt
.is-error
:user-invalid
pattern
maxlength
type=email
```

Phase 0 must classify:

```txt
manual error class:
  allowed static state

native :user-invalid:
  allowed native browser-driven state

custom validation message generation:
  plugin/theme/editor integration
```

### §4.5 — Textarea scope

Baseline includes textarea-specific selectors.

Phase 0 must settle whether Textarea is:

```txt
within Text field #16
```

Recommendation:

```txt
Yes. Treat textarea as a Text field variant, not a separate matrix row.
```

### §4.6 — Date/Time picker composition boundary

Text field is used by Date/Time picker docked/modal-input patterns, but Date picker #22 and Time picker #23 remain separate rows.

Phase 0 must state:

```txt
Text field owns:
  generic input shell, label, slots, helper/counter, native validity display

Date/Time owns:
  calendar/dial/grid/popup/dialog behavior
```

### §4.7 — WordPress form boundary

Phase 0 must classify likely mapping paths:

```txt
core/search:
  related to Search bar #17, not generic Text field

core/post-comments-form:
  possible text/textarea contexts, but WordPress owns form semantics

core/html / pattern composition:
  possible static theme pattern path

plugin/editor:
  custom field schema, validation messages, submission logic
```

Recommendation:

```txt
WP-MAPPING should emphasize theme-can / plugin-should:
  theme can style native form controls and patterns
  plugin/editor should own schema, dynamic validation, persistence
```

---

## §5 — Dependency Profile Expected

Expected Phase 0 classification:

| Provider | Consumer state | Notes |
|---|---|---|
| `components.css §9` | CURRENT | baseline Text field primitive |
| `components.css §0` | CURRENT partial | focus-visible policy exists, but Text field uses its own active indicator and focused label behavior |
| `icon-system/` | CURRENT conditional | optional leading/trailing/error icons and composed trailing icon-button |
| `ripple/` | NONE for field; TARGET only through composed icon-button | Text field host itself does not use animated ripple |
| `popover/` | NONE for Text field; composition for Date/Time or dropdown fields | Date/Time and Select-like behavior belong elsewhere |

Phase 0 should be careful not to over-promote dependencies just because Text field composes other controls.

---

## §6 — Playwright-Assisted QA Integration

v3.5.6 introduced Playwright-assisted visual QA. Text field must adopt it from the start.

Phase 0 report should require:

```txt
Phase 2 implementation:
  - After pattern HTML is written, run Playwright dimension checks.
  - Verify wrapper/container/input/label/slot class names exactly match
    baseline selectors.
  - Check filled and outlined single-line container height = 56px.
  - Check textarea container min-height and label position.
  - Check leading/trailing icon grid placement.
  - Check helper/counter bottom row alignment.

Phase 3 visual QA:
  - Playwright screenshot + computed-style pre-filter.
  - User eye check remains final verdict.
```

QA artifacts:

```txt
docs/**/*-qa.png
docs/**/qa-*.png
```

are gitignored and must not become release artifacts.

---

## §7 — Baseline Class-Name Precision Rule

Text field has many slot classes and state selectors. Phase 0 must add an explicit rule:

```txt
Before authoring pattern HTML:
  grep baseline selectors

During authoring:
  copy class names exactly

After authoring:
  run Playwright + selector count checks
```

This is a direct carry-forward from v3.5.6:

```txt
Tab bug:
  invalid .tab demo class looked plausible but did not receive baseline
  .tabs__tab 48px/flex sizing.

Text field risk:
  invalid .text-field__helper or .input-field would look plausible but miss
  baseline selector behavior.
```

---

## §8 — Expected Risks

### Risk 1 — Dual category drift

Text field is Component Full-Spec + Interaction. Phase 0 may over-expand into runtime JS.

Disposition:

```txt
Classify native/CSS interaction separately from integration/runtime behavior.
Do not create a JS runtime unless Phase 0 finds an actual public runtime API.
```

### Risk 2 — Label/container markup invalidity

Baseline explicitly moved from label-as-container to:

```txt
div.text-field__container
input.text-field__input
label.text-field__label[for]
```

Disposition:

```txt
Phase 0 must preserve the HTML5 constraint: labelable controls such as
buttons cannot be nested inside a label container.
```

### Risk 3 — Slot DOM-order dependency

Baseline selectors depend on input-first DOM order:

```txt
input ~ label
input ~ prefix
input ~ suffix
input ~ error-icon
```

Disposition:

```txt
Phase 0 must record DOM order as part of the contract, not an implementation detail.
```

### Risk 4 — `:has()` support and portability

Text field baseline uses `:has()` for native validity/disabled and textarea selectors.

Disposition:

```txt
Phase 0 must classify this as current baseline reality and note any WordPress/browser portability implications.
```

### Risk 5 — Clear/counter behavior scope creep

Clear button and counter look like runtime features.

Disposition:

```txt
Static visual and CSS visibility only in v3.5.7 unless explicitly approved.
Dynamic clearing/counter update belongs to integration.
```

### Risk 6 — Icon-system dependency overstatement

Text field often contains icons, but it is not icon-defined.

Disposition:

```txt
icon-system/ = CURRENT conditional, not unconditional.
```

### Risk 7 — Date/Time picker absorption

Text field appears inside Date/Time picker patterns.

Disposition:

```txt
Do not absorb Date picker #22 or Time picker #23. Text field owns only the generic input shell.
```

### Risk 8 — Public specimen stale SVG snippets

Text field current public specimens still include inline SVG in leading/error snippets.

Disposition:

```txt
Phase 0 should record whether this is a v3.5.7 cleanup candidate or route it
to the same public specimen cleanup family as Icon button #28.
```

---

## §9 — Phase 1 Entry Conditions

Phase 1 plan may start only after Phase 0 report settles:

```txt
1. Audit doc shape: 3-doc trio only vs fourth interaction doc.
2. Textarea inclusion.
3. Clear button behavior scope.
4. Counter behavior scope.
5. Native validation boundary.
6. Dependency profile and consumer-state classification.
7. WordPress form/plugin boundary.
8. Playwright QA expectations.
9. Public SVG snippet cleanup routing.
```

---

## §10 — Non-Goals

Not in Phase 0:

```txt
- Create lab/modules/text-field/
- Write TEXT-FIELD-SPEC-AUDIT.md
- Write TEXT-FIELD-MEASUREMENT-AUDIT.md
- Write TEXT-FIELD-WP-MAPPING.md
- Create lab-text-field.css
- Create lab-text-field-pattern.html
- Create lab-text-field.js
- Modify components.css §9
- Modify style-guide.html #components-text-field
- Rename .text-field classes
- Replace inline SVG snippets
- Implement clear-button JS
- Implement live counter JS
- Implement custom validation engine
- Implement Date picker / Time picker behavior
- Implement Search bar
- Modify CURRENT-STATE.md
- Modify NEXT-SESSION.md
- Close BACKLOG items
- Add release CHANGELOG / ROADMAP entries
```

---

## §11 — Validation Plan

After Phase 0 report execution:

```txt
python .\tools\validators\validate_theme_pilot.py
```

Expected:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

Also verify:

```txt
baseline mtimes unchanged:
  components.css
  style-guide.html
  tokens.css
  blocks.css

state/handoff untouched:
  CURRENT-STATE.md
  NEXT-SESSION.md

no module directory:
  products/reference-implementations/axismundi-lab/modules/text-field/
```

---

## §12 — Approval Gate

This plan is ready for review.

If approved:

```txt
Codex executes Phase 0 report:
  docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md
```

If revised:

```txt
Update only this plan.
Do not touch state/handoff files.
```

