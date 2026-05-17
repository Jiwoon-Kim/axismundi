# Axismundi v3.5.7 — Text Field #16 Phase 2 Plan

> **Status**: PLAN-ONLY v1.0. Awaiting review/approval before Phase 2 execution.  
> **Date**: 2026-05-16  
> **Source authority**: `docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md` + Text field Phase 1 audit trio.  
> **Reference template**: Button v3.5.1, Icon button v3.5.2, Card v3.5.3, FAB v3.5.5 Phase 2 plans.  
> **Pre-entry decision**: Text field interaction is native/CSS state behavior; clear/counter are static; no JS runtime in v3.5.7.

---

## §0 — Plan Scope And Gate

This is a plan-only artifact. It does not create Phase 2 deliverables.

Purpose:

```txt
1. Define exact Phase 2 deliverables.
2. Lock selector policy and non-goals.
3. Define the Text field pattern HTML section structure.
4. Carry dual-category Interaction coverage into Playwright QA.
5. Map validation and G1-G16 gate readiness.
```

Approval gate:

```txt
Phase 2 execution does not begin until User approves this plan.
```

---

## §1 — Lock Decisions

### §1.1 — Deliverables

Phase 2 creates exactly two deliverable artifacts:

| File | Path | Role |
|---|---|---|
| `lab-text-field.css` | `products/reference-implementations/axismundi-lab/modules/text-field/lab-text-field.css` | Lab-internal demo scaffolding and documentation comments on top of baseline `.text-field`. |
| `lab-text-field-pattern.html` | `products/reference-implementations/axismundi-lab/modules/text-field/lab-text-field-pattern.html` | Full variants/states/slots/native validation pattern page for Text field. |

Not created:

```txt
lab-text-field.js
```

Reason:

```txt
Text field interaction in v3.5.7 is native/CSS state behavior:
  label float
  focus-within active indicator
  :placeholder-shown
  :user-invalid
  :disabled
  :has()

Clear-click and live counter behavior are integration territory and remain
static catalog specimens in this release.
```

Phase bookkeeping:

```txt
Optional after successful execution:
  TEXT-FIELD-SPEC-AUDIT.md §15 criterion #3 (Pattern HTML completeness)
  -> PASS at Phase 2 level.

Do not update CURRENT-STATE.md unless the user asks for a phase-boundary
snapshot. Do not update NEXT-SESSION.md unless ending or handing off the
session.
```

### §1.2 — Selector Policy

Decision:

```txt
Use .lab-text-field-demo as the lab page scope marker.
```

Allowed:

```txt
.lab-text-field-demo layout scaffolding
.lab-text-field-demo .text-field[...] visualization-only helpers
module-private --lab-text-field-* layout variables
comments declaring dependency profile and audit references
Playwright-oriented data attributes on demo wrappers
```

Forbidden:

```txt
unscoped .text-field overrides
unscoped .text-field--filled overrides
unscoped .text-field--outlined overrides
unscoped .text-field__* overrides
new size variants
new behavior states
ripple wiring on .text-field__container
JavaScript dependencies
```

Scoped visualization helpers, if used, must be limited to:

```txt
demo grid/layout
caption affordance
static focus/error/disabled teaching wrappers
QA hooks
```

They must not change baseline component measurements.

### §1.3 — Runtime Boundary

Decision:

```txt
NO lab-text-field.js in v3.5.7.
```

Static-only specimens:

```txt
clear button
counter
native validation examples
readonly if present
aria-disabled plugin-managed state
```

Caption requirement:

```txt
Static catalog specimen — no JavaScript behavior is wired here.
Production usage wires behavior at the theme, block editor, or plugin level.

정적 카탈로그 specimen — 여기에는 JavaScript 동작을 배선하지 않는다.
실제 사용 시 theme / block editor / plugin 레벨에서 behavior를 배선한다.
```

### §1.4 — Dependency Boundary

Phase 2 pattern must preserve:

```txt
icon-system/:
  CURRENT conditional through optional slots

ripple/:
  NONE for .text-field__container
  TARGET only through composed trailing icon-button if that icon-button opts in

popover/:
  NONE for generic Text field
```

Pattern HTML may show a composed clear icon-button with its normal classes, but must not put `data-ax-ripple` on the field host.

---

## §2 — lab-text-field.css Plan

`lab-text-field.css` is demo scaffolding only.

Expected size:

```txt
120-220 lines
```

Allowed content:

```txt
header comment block with scope and non-goals
.lab-text-field-demo page layout
.lab-text-field-grid
.lab-text-field-row
.lab-text-field-section
.lab-text-field-caption
.lab-text-field-note
.lab-text-field-snippet
.lab-text-field-checklist
demo-only max-width wrappers
demo-only vertical spacing
```

Allowed scoped visualization helpers:

```txt
.lab-text-field-demo [data-demo-state="focused"] ...
.lab-text-field-demo [data-demo-state="native-invalid"] ...
```

Only if they are explicitly documented as visualization-only and do not override baseline measurements.

Forbidden:

```txt
.text-field { ... }
.text-field--filled { ... }
.text-field--outlined { ... }
.text-field__container { ... }
.text-field__input { ... }
.text-field__label { ... }
```

Any selector touching `.text-field` must be scoped under `.lab-text-field-demo` and must be layout/visualization-only.

---

## §3 — lab-text-field-pattern.html Plan

Expected size:

```txt
500-650 lines
```

Use this section structure:

```txt
§1 Header + status / dependency boundary
§2 Filled variant matrix
§3 Outlined variant matrix
§4 Native/CSS states
§5 Slots: leading / trailing / prefix / suffix
§5a Composed trailing icon-button
§6 Supporting text + static counter
§7 Clear button static specimen
§7a Disabled — native
§7b Disabled — aria-disabled plugin-managed
§8 Textarea variant
§9 Native input types showcase
§10 Playwright QA targets
§11 Code snippets
§12 Cross-references
```

### §3.1 — Header + status

Must state:

```txt
Text field v3.5.7 Phase 2 pattern.
Native/CSS interaction only.
No lab-text-field.js.
No ripple on field host.
Clear/counter are static specimens.
```

### §3.2 — Filled variant matrix

Minimum filled specimens:

```txt
empty
populated
focused/static catalog
manual error
disabled native
with supporting text
with counter
```

### §3.3 — Outlined variant matrix

Minimum outlined specimens:

```txt
empty
populated
focused/static catalog
manual error
disabled native
with leading icon
with trailing clear icon
with prefix/suffix
```

### §3.4 — Native/CSS states

Must show:

```txt
:focus-within label float
:placeholder-shown empty state
:user-invalid native validation
:disabled
required
readonly if baseline-supported
```

Native validation specimen:

```html
<input class="text-field__input"
       type="email"
       required
       placeholder=" ">
```

Caption must explain:

```txt
Native browser validation drives :user-invalid after user interaction.
No custom validation JS is wired here.
```

### §3.5 — Slots

Required slots:

```txt
leading icon
trailing clear icon
error icon
prefix
suffix
supporting text
counter
```

Class-name precision:

```txt
Use exactly:
  .text-field__leading-icon
  .text-field__trailing-icon
  .text-field__error-icon
  .text-field__prefix
  .text-field__suffix
  .text-field__bottom
  .text-field__supporting
  .text-field__counter
```

### §3.6 — Composed trailing icon-button

Required specimen:

```html
<button type="button"
        class="ax-icon-button is-standard has-state-layer text-field__trailing-icon is-clear"
        aria-label="Clear">
  <span class="material-symbols-rounded notranslate"
        translate="no"
        aria-hidden="true"
        draggable="false">close</span>
</button>
```

Caption:

```txt
The clear button is a composed Icon button. Text field owns placement;
Icon button owns control semantics and target geometry. Clear-click behavior
is not wired in v3.5.7.
```

### §3.7 — Supporting text + static counter

Required:

```txt
helper-only
counter-only
helper + counter
error helper + counter
```

Counter caption:

```txt
Static counter specimen — no live character count is wired here.
Production usage wires dynamic counting at the theme, editor, or plugin level.
```

### §3.8 — Disabled split

Native disabled:

```html
<input class="text-field__input" disabled>
```

aria-disabled plugin-managed:

```html
<div class="text-field text-field--filled" aria-disabled="true">...</div>
```

Caption:

```txt
aria-disabled communicates state and visual styling only. Integrator code
must guard editing, events, and submission.
```

### §3.9 — Textarea

Required specimens:

```txt
outlined textarea
textarea with supporting text
textarea with static counter
disabled textarea
```

Do not implement:

```txt
auto-grow runtime
rich text editor
markdown editor
```

### §3.10 — Native input types

Show safe examples:

```txt
text
email
url
tel
password
number or inputmode numeric
search as generic input shell only
date/time as native input shell only, not Date/Time picker
```

Do not overpromise browser-native styling consistency for date/time inputs.

---

## §4 — Label Transition And Native State QA

Phase 2 implementation must preserve baseline label transition.

Playwright checks:

```txt
rest:
  label font-size = body-large computed value
  label top/transform indicates centered rest state

focus:
  label font-size = body-small computed value
  label moves to floated position

populated:
  label stays floated without focus

prefix/suffix:
  hidden at rest when empty
  visible after focus or populated
```

Do not add lab CSS that changes baseline transition timing or label geometry.

---

## §5 — Playwright QA Plan

Phase 2 post-write checks:

```txt
1. Selector presence:
   .text-field
   .text-field__container
   .text-field__input
   .text-field__label
   .text-field__bottom

2. Dimensions:
   filled container height = 56px
   outlined container height = 56px
   textarea min-height >= 96px

3. Label:
   rest vs focus computed style changes
   populated label stays floated

4. Native state:
   :user-invalid / manual .is-error color evidence
   disabled visual treatment

5. Slots:
   leading/trailing icon grid placement
   clear icon-button 40px geometry
   helper/counter non-overlap

6. Keyboard:
   Tab reaches native fields and clear button
   Arrow keys remain native text-editing behavior, not custom roving focus
```

Phase 3 formal QA:

```txt
Playwright screenshot + computed-style pre-filter.
User eye check remains final verdict.
```

QA artifacts:

```txt
docs/v3.5.7/*-qa.png
docs/v3.5.7/qa-*.png
```

These are gitignored.

---

## §6 — WCAG / SC Checks To Preserve

Phase 2 pattern and Playwright notes must preserve MEASUREMENT §10.0:

```txt
SC 1.4.3   Contrast Minimum
SC 1.4.11  Non-text Contrast
SC 1.3.5   Identify Input Purpose
SC 3.3.1   Error Identification
SC 3.3.2   Labels or Instructions
SC 3.3.3   Error Suggestion
SC 4.1.3   Status Messages
SC 2.5.8   Target Size Minimum — N/A for field host, applies to clear button
SC 2.5.5   Target Size Enhanced — N/A for field host, applies to clear button
```

Phase 2 does not need to solve async `aria-live` behavior. If live validation/counter is later implemented, plugin/editor code owns status-message wiring.

---

## §7 — Phase Bookkeeping

After successful Phase 2 execution, optional bookkeeping:

```txt
TEXT-FIELD-SPEC-AUDIT.md §15:
  criterion #3 Pattern HTML completeness -> PASS at Phase 2 level
```

Do not update:

```txt
CURRENT-STATE.md
NEXT-SESSION.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md
```

Reason:

```txt
Phase 2 execution is not a release close, and session/state handoff documents
should not churn during micro-steps.
```

---

## §8 — File Scope

Allowed after approval:

```txt
products/reference-implementations/axismundi-lab/modules/text-field/lab-text-field.css
products/reference-implementations/axismundi-lab/modules/text-field/lab-text-field-pattern.html
products/reference-implementations/axismundi-lab/modules/text-field/docs/TEXT-FIELD-SPEC-AUDIT.md
```

The SPEC edit is limited to Phase 2 criterion bookkeeping after successful execution.

Forbidden:

```txt
products/reference-implementations/axismundi-lab/modules/text-field/lab-text-field.js
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

## §9 — Non-goals

Not in Phase 2:

```txt
- Create lab-text-field.js.
- Implement clear-click behavior.
- Implement live counter behavior.
- Implement custom validation runtime.
- Implement async validation.
- Implement Date picker.
- Implement Time picker.
- Implement Search bar expansion.
- Implement Select/dropdown/popover behavior.
- Add data-ax-ripple to .text-field__container.
- Import Material Web.
- Add <md-filled-text-field> or <md-outlined-text-field>.
- Edit baseline components.css §9.
- Edit style-guide.html #components-text-field.
- Edit tokens.css.
- Edit blocks.css.
- Register WordPress block styles.
- Edit functions.php.
- Edit state/handoff/release docs.
```

---

## §10 — Risks

| Risk | Description | Plan mitigation |
|---|---|---|
| R1 | Lab CSS accidentally overrides baseline `.text-field` | Scope all CSS under `.lab-text-field-demo` |
| R2 | Pattern HTML invents wrong class names | Baseline grep + exact class list required |
| R3 | Clear button feels broken | Static catalog caption required |
| R4 | Counter appears live but is static | Static counter caption required |
| R5 | `:user-invalid` is hard to trigger consistently | Include manual `.is-error` and native specimen; QA documents browser behavior |
| R6 | Date/Time/Search behavior creeps in | Native input shell only; sibling boundary repeated |
| R7 | ripple added to field host | Explicitly forbidden |
| R8 | Playwright misses user-visible label jitter | User eye check remains Phase 3 final verdict |
| R9 | aria-disabled mistaken for real disabled | §7b plugin-managed caption |
| R10 | Async/live validation expected | SC 4.1.3 remains plugin/editor territory |

---

## §11 — Validation Plan

After Phase 2 execution:

```txt
1. Confirm exactly two new deliverable artifacts:
   lab-text-field.css
   lab-text-field-pattern.html

2. Confirm no lab-text-field.js.

3. Confirm no baseline/state/handoff/release files changed.

4. Run validator:
   1.000 / 1.000 / 1.000 / 1.000 PASS

5. Run Playwright checks:
   selectors
   dimensions
   label transition
   slots
   native states
   screenshot

6. Confirm SPEC §15 criterion #3 bookkeeping if applied.
```

---

## §12 — Approval Gate

This plan does not authorize implementation until reviewed and approved.

Flow:

```txt
Plan v1.0
  -> review
  -> optional v1.1 revision
  -> approval
  -> Phase 2 execution
```

---

## §13 — One-line summary

```txt
Text field Phase 2 should create exactly two lab artifacts, preserve the
baseline `.text-field` contract, demonstrate filled/outlined/textarea states
with native/CSS interaction only, keep clear/counter static, and use
Playwright to verify dimensions, label transition, slots, and native states.
```

