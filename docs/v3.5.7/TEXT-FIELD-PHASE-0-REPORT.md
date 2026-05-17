# v3.5.7 — Text Field #16 — Phase 0 Report

Status: PHASE 0 COMPLETE  
Scope: Documentation-only inventory, category classification, and Phase 1 entry framing  
Date: 2026-05-16  
Matrix row: #16 Text field  
TOC group: Inputs  
Category: Component Full-Spec + Interaction  

---

## §0 — Phase 0 framing

Text field is the first Wave 1 Inputs-family entry after the v3.5.6 Ripple v2 foundation release. It is also the first Wave 1 component whose matrix category is explicitly dual:

```txt
Component Full-Spec + Interaction
```

That dual category is not a license to create a runtime module by default. Phase 0 distinguishes:

```txt
Component Full-Spec surface:
  variants, slots, measurements, states, native markup, WordPress mapping

Interaction surface:
  label transition, native validation states, focus/active indicator,
  clear button visibility, counter display, textarea behavior, form boundary

Runtime extraction:
  NOT discovered in Phase 0
```

Phase 0 verdict:

```txt
Text field should enter Phase 1 as a 3-document Component Full-Spec trio.
Interaction coverage belongs inside SPEC and WP-MAPPING, not in a fourth
runtime audit document, unless Phase 1 discovers an actual extracted runtime
API surface.
```

Documentation boundary:

```txt
Created:
  docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md

Not created:
  products/reference-implementations/axismundi-lab/modules/text-field/

Not edited:
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

---

## §1 — Authoritative inputs

### §1.1 — Canonical framework inputs

Phase 0 uses the existing v3.5.x framework:

```txt
CONSTITUTION.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

Rules applied here:

```txt
- Products/reference implementations consume the ontology; they do not define it.
- Public and lab tiers stay distinct.
- Baseline files are authoritative inputs and are not mutated by module cycles.
- Publishing surfaces are mirrors, not authorities.
- Component cycles use Phase 0 -> Phase 1 -> Phase 2 -> Phase 3 -> Phase 5.
- CURRENT-STATE.md changes only at phase boundaries.
- NEXT-SESSION.md changes only at session boundaries.
```

### §1.2 — Local baseline inputs

Phase 0 inspected:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
  §9 Text field
  Approximate source range: lines 930-1474

products/reference-implementations/axismundi-lab/style-guide.html
  #components-text-field
  Approximate source range: lines 2342-2551
```

These are canonical for current Axismundi behavior. Phase 1 should cite both line ranges again after any local line-number drift.

### §1.3 — External M3 references

External references for Phase 1:

```txt
Material Design 3 Text fields specs:
  https://m3.material.io/components/text-fields/specs

Material Design 3 Text fields guidelines:
  https://m3.material.io/components/text-fields/guidelines

Material Web text field implementation reference:
  https://material-web.dev/components/text-field/
```

Authority rule:

```txt
M3 specs/guidelines:
  design-system reference

Material Web text field:
  implementation comparison reference only
```

Material Web is not treated as an Axismundi dependency, and the user noted that Material Web is currently stale/under-maintained. Phase 1 may compare against Material Web for contract ideas, but must not import it or treat it as the authoritative implementation.

### §1.4 — Wave 1 precedents

Use these in order:

```txt
1. Button v3.5.1
   Variant/state audit pattern, Pattern A disabled split, native control principle.

2. Icon button v3.5.2
   Accessible-name strictness, icon-system unconditional contrast, runtime audit disposition.

3. Card v3.5.3
   Native semantics decision tree, composition slot handling, Pattern B disabled contrast.

4. FAB v3.5.5
   Static behavior deferral, icon-system unconditional contrast, family merge discipline.

5. Ripple v3.5.6
   Playwright-assisted visual QA, baseline class-name precision, dependency-state alignment.

6. Chip v3.4.9
   Original Component Full-Spec 3-doc template.
```

---

## §2 — Baseline §9 inventory

### §2.1 — Section shape

`components.css §9 Text field` is a mature baseline implementation. It includes:

```txt
- Markup contract comment block
- Accessibility and HTML standards note
- DOM order note
- Floating label explanation
- Prefix/suffix visibility rules
- Validation rules
- Active indicator rules
- Outlined notch backdrop caveat
- Icon and clear button composition rules
- Wrapper/container/input/label/slot styles
- Filled and outlined variants
- Manual and native error states
- Disabled states
- Textarea support
```

Approximate implementation size:

```txt
components.css §9 span:
  ~507 lines

Rough selector/rule density:
  ~120 selector-like entries
```

### §2.2 — Selector inventory

Observed selector-class counts in `components.css`:

| Selector / pattern | Count | Notes |
|---|---:|---|
| `.text-field` | 107 | wrapper + state selectors |
| `.text-field__container` | 34 | visual container |
| `.text-field__input` | 40 | input/textarea core |
| `.text-field__label` | 19 | real label |
| `.text-field__leading-icon` | 7 | optional slot |
| `.text-field__trailing-icon` | 10 | optional slot / composed icon button |
| `.text-field__error-icon` | 7 | error-only slot |
| `.text-field__prefix` | 5 | affix slot |
| `.text-field__suffix` | 7 | affix slot |
| `.text-field__bottom` | 1 | supporting/counter row |
| `.text-field__supporting` | 5 | helper/error text |
| `.text-field__counter` | 6 | counter display |
| `.text-field--filled` | 9 | filled variant |
| `.text-field--outlined` | 15 | outlined variant |
| `.text-field--with-leading` | 3 | leading icon offset modifier |
| `.is-clear` | 3 | clear-button visibility modifier |
| `textarea.text-field__input` | 7 | textarea variant |

State and structural pseudo-class usage:

| Pattern | Count | Phase 0 classification |
|---|---:|---|
| `:has(` | 28 | CURRENT baseline dependency; must be QA'd in Phase 2/3 |
| `:placeholder-shown` | 10 | label float / empty detection |
| `:user-invalid` | 16 | native validation state |
| `:disabled` | 64 | native disabled handling |

### §2.3 — Baseline class-name contract

Phase 1 and Phase 2 must copy these names exactly:

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

Forbidden invented names:

```txt
.textfield
.textinput
.input-field
.text-field__body
.text-field__helper
.text-field__content
```

This carries forward the v3.5.6 Tab lesson: plausible class names are not enough. Pattern HTML must match baseline selectors exactly.

### §2.4 — Markup contract

Current baseline contract:

```html
<div class="text-field text-field--filled">
  <div class="text-field__container">
    <input class="text-field__input" id="tf-id" placeholder=" " />
    <label class="text-field__label" for="tf-id">Email</label>
    <span class="text-field__leading-icon">...</span>
    <span class="text-field__prefix">$</span>
    <span class="text-field__suffix">USD</span>
    <button class="ax-icon-button is-standard has-state-layer text-field__trailing-icon">
      ...
    </button>
    <span class="text-field__error-icon">...</span>
  </div>
  <div class="text-field__bottom">
    <span class="text-field__supporting">Helper text</span>
    <span class="text-field__counter">0 / 280</span>
  </div>
</div>
```

Critical baseline rules:

```txt
- .text-field__container is a <div>, not a <label>.
- .text-field__label is a real <label> with explicit for/id pairing.
- .text-field__input must come first in DOM order.
- Prefix/suffix/label slot behavior depends on general sibling selectors.
- Interactive trailing icons compose ax-icon-button; Text field owns placement.
- The field host itself does not become an icon button or ripple target.
```

### §2.5 — Private tokens and measurements

Baseline private variables:

```txt
--_tf-h: 56px
--_tf-px: var(--space-md)
--_tf-label-rest-size: body-large
--_tf-label-rest-lh: body-large line-height
--_tf-label-float-size: body-small
--_tf-label-float-lh: body-small line-height
```

Current baseline size:

```txt
Single-line filled/outlined text field visual container:
  min-height: 56px

Textarea:
  min-height: 96px
  resize: vertical
```

Phase 1 MEASUREMENT must verify these values against M3 reference and local baseline. Phase 2 Playwright must measure actual rendered container heights.

### §2.6 — Variant inventory

Current baseline variants:

```txt
Filled:
  .text-field.text-field--filled

Outlined:
  .text-field.text-field--outlined

Leading icon:
  .text-field--with-leading

Textarea:
  textarea.text-field__input inside standard .text-field wrapper
```

No separate matrix row is needed for Textarea. It is a Text field variant.

### §2.7 — State inventory

Current baseline states:

```txt
Rest
Focused / focus-within
Populated / not(:placeholder-shown)
Manual error via .is-error
Native error via :user-invalid
Disabled via input:disabled
Wrapper aria-disabled="true" visual state
Hover for filled/outlined container
Clear-button hidden when empty via .is-clear + :placeholder-shown
```

Phase 1 must distinguish visual/static states from dynamic integration behavior.

---

## §3 — Public specimen inventory

### §3.1 — Specimen sections

`style-guide.html #components-text-field` currently includes:

```txt
1. Filled — 5 states
2. Outlined — 5 states
3. Prefix / Suffix / Counter / Native validation
4. Leading / Trailing icons
5. Textarea
```

Observed pattern counts:

| Pattern | Count | Notes |
|---|---:|---|
| `class="text-field` | 108 | wrapper appearances including snippets |
| `class="text-field__container` | 20 | specimen containers |
| `class="text-field__input` | 19 | input specimens + snippets |
| `class="text-field__label` | 19 | explicit labels |
| `class="text-field__leading-icon` | 3 | leading slot specimens/snippets |
| `class="text-field__trailing-icon` | 0 | literal class-start count only; see §3.3 |
| `class="text-field__error-icon` | 6 | error icon specimens/snippets |
| `class="text-field__prefix` | 2 | prefix specimen/snippet |
| `class="text-field__suffix` | 2 | suffix specimen/snippet |
| `class="text-field__bottom` | 9 | supporting/counter rows |
| `class="text-field__supporting` | 8 | helper/error text |
| `class="text-field__counter` | 2 | static counters |
| `textarea class="text-field__input` | 2 | textarea specimen/snippet |

State and native attributes:

| Pattern | Count | Notes |
|---|---:|---|
| `disabled` | 48 | includes specimens and snippets |
| `.is-error` | 4 | manual error specimens |
| `.is-clear` | 3 | clear button |
| `type="email"` | 1 | native email validation |
| `pattern=` | 2 | numeric validation |
| `maxlength=` | 8 | counter/textarea constraints |

### §3.2 — Filled and outlined coverage

Filled coverage:

```txt
Empty
Populated
Focused (manual specimen label)
Error
Disabled
```

Outlined coverage:

```txt
Empty
Populated
Focused (manual specimen label)
Error
Disabled
```

Phase 1 SPEC should preserve this 5-state grid. Phase 2 pattern HTML may expand it but must not collapse these basic states.

### §3.3 — Trailing icon count caveat

The literal search:

```txt
class="text-field__trailing-icon
```

returns 0 because the live clear button uses multi-class ordering:

```html
<button type="button"
        class="ax-icon-button is-standard has-state-layer text-field__trailing-icon is-clear"
        aria-label="Clear">
```

This is valid and important. Text field trailing action is a composed Icon button. Phase 1 should audit the composed control contract, not rewrite class ordering merely to satisfy naive grep.

### §3.4 — Inline SVG caveat

Current public specimens still include inline SVG for several leading/error icons. That is not a Phase 0 blocker, but it is a candidate future cleanup item:

```txt
Potential future BACKLOG candidate:
  replace stale inline SVG snippets in Text field specimens with icon-system
  / Material Symbols pattern where appropriate.
```

Phase 0 does not edit BACKLOG.md.

---

## §4 — Category classification: Component Full-Spec + Interaction

### §4.1 — Matrix row

`MODULE-STATUS-MATRIX.md` row #16:

```txt
Text field
TOC Group: Inputs
Category: Component Full-Spec + Interaction
Status: TODO
Target: text-field/
Dependency type: Independent
Wave: 1
Notes: Filled + outlined; label transition; error/help states.
       Most complex M3 component.
```

### §4.2 — Component Full-Spec surface

Text field qualifies for Component Full-Spec because it needs:

```txt
- SPEC audit
- MEASUREMENT audit
- WP-MAPPING audit
- Lab pattern artifact
- Static Visual QA
- WCAG/native semantics review
- WordPress/form boundary mapping
```

Expected Phase 1 deliverables:

```txt
products/reference-implementations/axismundi-lab/modules/text-field/docs/
  TEXT-FIELD-SPEC-AUDIT.md
  TEXT-FIELD-MEASUREMENT-AUDIT.md
  TEXT-FIELD-WP-MAPPING.md
```

### §4.3 — Interaction surface

Text field interaction is real, but current evidence does not justify a separate runtime module:

```txt
Native/CSS interaction:
  label float
  focus-within active indicator
  :placeholder-shown empty detection
  :user-invalid native validation state
  disabled visual state
  error icon mutual exclusion
  clear button empty-state visibility
  textarea layout behavior

Integration/runtime behavior:
  clear button click handler
  dynamic character counter
  custom validation messages
  async validation
  form submission handling
  dropdown/date-picker/calendar/dial popovers
```

Decision:

```txt
No fourth interaction audit doc in v3.5.7 Phase 1.

Interaction readiness belongs inside TEXT-FIELD-SPEC-AUDIT.md and
TEXT-FIELD-WP-MAPPING.md. Create a separate interaction/runtime audit only if
Phase 1 discovers an actual reusable JS API surface.
```

---

## §5 — Native markup + accessibility contract

### §5.1 — Real controls

Text field must use real native form controls:

```txt
Single-line:
  <input class="text-field__input">

Multi-line:
  <textarea class="text-field__input">

Label:
  <label class="text-field__label" for="...">
```

Forbidden:

```txt
<div role="textbox" class="text-field__input">
<span contenteditable class="text-field__input">
<label class="text-field__container"> ... <button> ... </label>
```

The current baseline already fixed the wrapper split so that button descendants do not live inside a label.

### §5.2 — Label/id contract

Every live specimen must provide:

```txt
input/textarea id
label for="<same id>"
```

The floating label is visual, but it must also be the accessible label through native HTML association.

### §5.3 — Placeholder contract

Baseline uses:

```html
placeholder=" "
```

Reason:

```txt
The placeholder is not user-facing help text. It is a CSS state hook for
:placeholder-shown so label float can detect empty state without JavaScript.
```

Phase 1 must document this to prevent authors from replacing labels with placeholders.

### §5.4 — Supporting text and error text

`.text-field__supporting` may be helper text or error text. Phase 1 WP-MAPPING should decide whether pattern snippets should add `aria-describedby` for live accessibility examples.

Phase 0 recommendation:

```txt
Phase 1 should require aria-describedby in audit-recommended snippets when
supporting/error text is semantically connected to the field.
```

This may go beyond the current public specimen, but it is a documentation/audit requirement, not a baseline mutation.

### §5.5 — Disabled and aria-disabled

Native field disabled state:

```html
<input class="text-field__input" disabled>
```

Wrapper visual state:

```html
<div class="text-field" aria-disabled="true"> ... </div>
```

Decision:

```txt
Phase 1 should use native disabled for actual disabled input/textarea
specimens. aria-disabled wrapper state may be documented as plugin-managed
visual state only and must not replace native disabled where native disabled
is available.
```

---

## §6 — Variant/state/slot coverage

### §6.1 — Required variants for Phase 1

Phase 1 SPEC should cover at minimum:

```txt
Filled
Outlined
Filled with leading icon
Outlined with leading icon
Prefix/suffix
Counter
Manual error
Native validation error
Disabled
Textarea
```

### §6.2 — Clear button scope

Decision:

```txt
Clear button is visual/static in v3.5.7.
```

Allowed:

```txt
- Show clear button slot.
- Use .is-clear to demonstrate empty-state auto-hide via CSS.
- Compose with ax-icon-button for the trailing action.
- Label the specimen as a static catalog specimen.
```

Not in v3.5.7 unless separately approved:

```txt
- lab-text-field.js
- click handler that clears input value
- dynamic reflow after clearing
- event API for clear action
```

### §6.3 — Counter scope

Decision:

```txt
Counter is static visual in v3.5.7.
```

Allowed:

```txt
<span class="text-field__counter">0 / 50</span>
data-tf-counter as specimen metadata
maxlength on input/textarea
```

Not in v3.5.7 unless separately approved:

```txt
Live JavaScript counter
Form library integration
Async validation/counter synchronization
```

### §6.4 — Native validation scope

Allowed in v3.5.7:

```txt
Manual .is-error static state
Native :user-invalid state
type="email"
pattern
maxlength
required
disabled
```

Integration/plugin territory:

```txt
Custom message generation
Async validation
Server validation
Field schema persistence
Block editor controls
```

### §6.5 — Textarea scope

Decision:

```txt
Textarea belongs inside Text field #16 as a variant.
```

Reason:

```txt
Baseline selectors already support textarea.text-field__input, and the matrix
does not define a separate Textarea row.
```

### §6.6 — Date/Time boundary

Decision:

```txt
Text field owns:
  generic input shell
  label
  slots
  helper/counter row
  native validity display

Date picker / Time picker own:
  calendar grid
  dial
  modal/docked surface
  popover/dialog behavior
  date/time parsing and selection logic
```

Text field may be composed by Date/Time picker, but it does not absorb those components.

---

## §7 — Dependency profile / consumer-state

| Provider | Consumer state | Phase 0 decision |
|---|---|---|
| `components.css §9` | CURRENT | Text field baseline primitive |
| `components.css §0` | CURRENT partial | Shared focus/state policy exists, but Text field uses its own active indicator and label behavior |
| `icon-system/` | CURRENT conditional | Optional leading/trailing/error icons and composed trailing icon-button |
| `ripple/` | NONE for field host | Field focus/error states do not use animated ripple |
| `ripple/` through composed icon-button | TARGET only for the composed icon-button | The icon button owns its own ripple behavior |
| `popover/` | NONE for generic Text field | Date/Time/dropdown/select behaviors live in their own rows/modules |
| `search-expansion/` | NONE | Search bar #17 owns search-specific affordances |

### §7.1 — icon-system dependency nuance

Text field is unlike Icon button and FAB:

```txt
Icon button:
  icon-system = CURRENT unconditional

FAB:
  icon-system = CURRENT unconditional

Text field:
  icon-system = CURRENT conditional
```

The dependency is slot-driven. A label-only Text field without icons remains a valid Text field.

### §7.2 — ripple dependency nuance

Text field is unlike Button/Icon button/FAB:

```txt
Text field host:
  ripple = NONE

Trailing clear icon button:
  ripple = whatever icon-button owns
```

Do not put `data-ax-ripple` on `.text-field__container` in Phase 2.

---

## §8 — Interaction boundary map

### §8.1 — Native/CSS interaction stays in Text field

Text field owns:

```txt
Label float
Focus-within active indicator
Hover visual states
Native invalid visual state
Manual error visual state
Disabled visual state
Clear button visibility when empty
Prefix/suffix visibility after label float
Textarea label and layout behavior
```

These are interaction surfaces but not extracted runtime APIs.

### §8.2 — Integration behavior is outside Phase 2 unless approved

Integration/plugin/theme/editor territory:

```txt
Clear button click behavior
Live character counting
Custom validation message generation
Async validation
Server validation
Form submission
Schema persistence
Autocomplete logic
Date/time picker popover/dial/calendar
Dropdown/select menu behavior
```

### §8.3 — Fourth doc decision

Decision:

```txt
No fourth interaction audit doc for v3.5.7 Phase 1.
```

Reason:

```txt
The interaction surface is native/CSS and should be audited in SPEC.
No independent runtime module comparable to Snackbar, Tooltip, Popover, or
Ripple has been discovered.
```

---

## §9 — WordPress/form mapping stub

### §9.1 — Expected mapping paths

Phase 1 WP-MAPPING should inventory:

```txt
core/post-comments-form:
  comment textarea / author fields / email field contexts

core/search:
  related, but primarily Search bar #17 territory

core/html:
  static pattern composition path

pattern composition:
  theme-authored field patterns in templates

plugin/editor:
  custom field schema, validation, persistence, submissions
```

### §9.2 — Theme-can / plugin-should boundary

Theme can:

```txt
Style native input/textarea controls.
Provide static pattern markup.
Provide visual states.
Provide typography, spacing, and token alignment.
```

Plugin/editor should:

```txt
Own field schema.
Own custom validation logic.
Own submission handling.
Own persistence.
Own dynamic counter and clear-click behavior when tied to data.
Own block editor controls for arbitrary custom fields.
```

### §9.3 — Anti-patterns for Phase 1 WP-MAPPING

Phase 1 should include an anti-pattern inventory:

```txt
- Placeholder-only label with no real <label>.
- .text-field__container as <label> containing buttons.
- <div role="textbox"> instead of input/textarea.
- JavaScript-only validation with no native fallback.
- Clear button with no accessible name.
- Counter text with no maxlength/source of truth.
- Date picker/calendar behavior folded into generic Text field.
- Search-specific expansion folded into generic Text field.
- Inline SVG icon snippets used where icon-system pattern should be used.
```

---

## §10 — Playwright + baseline class-name QA plan

### §10.1 — Phase 2 Playwright checks

After Phase 2 pattern HTML is written, run Playwright checks for:

```txt
Class-name precision:
  .text-field
  .text-field__container
  .text-field__input
  .text-field__label
  .text-field__bottom
  slot class names

Dimensions:
  filled single-line container = 56px height
  outlined single-line container = 56px height
  textarea min-height >= 96px

Layout:
  label position at rest/focused/populated
  leading icon column
  trailing icon-button placement
  prefix/suffix visibility
  bottom row helper/counter alignment

States:
  disabled
  .is-error
  :user-invalid where testable
  clear button hidden when empty
```

### §10.2 — Phase 3 visual QA

Phase 3 gate:

```txt
Playwright screenshot + computed-style pre-filter
User eye check as final verdict
```

Gitignored QA artifacts:

```txt
docs/**/*-qa.png
docs/**/qa-*.png
```

### §10.3 — Baseline grep rule

Before Phase 2 authoring:

```txt
grep components.css §9 selectors
grep style-guide.html #components-text-field specimens
copy class names exactly
```

This is mandatory for Text field because the slot grid depends on exact selectors and DOM order.

---

## §11 — Risks + dispositions

| Risk | Description | Disposition |
|---|---|---|
| R1 | Dual category causes over-extracted runtime doc | Settle: 3-doc trio; interaction in SPEC/WP-MAPPING |
| R2 | Container accidentally becomes `<label>` again | SPEC must preserve div container + explicit label/id contract |
| R3 | DOM order drift breaks sibling selectors | Phase 2 class/DOM precision checks required |
| R4 | Clear button click behavior scope creep | Static visual only in v3.5.7 unless separately approved |
| R5 | Counter becomes implicit JS requirement | Static counter only; live update integration territory |
| R6 | Textarea treated as separate component | Settle: Textarea is Text field variant |
| R7 | Date/Time picker absorbed into Text field | Settle: Text field owns shell only; Date/Time owns picker behavior |
| R8 | icon-system dependency over-promoted | Settle: CURRENT conditional, not unconditional |
| R9 | ripple incorrectly applied to field host | Settle: ripple NONE for field host; composed icon-button owns ripple |
| R10 | WordPress mapping overclaims theme territory | WP-MAPPING must use theme-can / plugin-should split |
| R11 | Inline SVG snippets remain in public specimens | Candidate future cleanup; not Phase 0 blocker |
| R12 | M3/Material Web reference drift | Use M3 as design reference; Material Web as secondary implementation reference only |
| R13 | Playwright not used early enough | Phase 2 and Phase 3 must include Playwright checks |

### §11.1 — Potential future BACKLOG candidates

Phase 0 does not edit BACKLOG.md, but it surfaces candidates:

```txt
Candidate A:
  Text field inline SVG specimen cleanup.

Candidate B:
  Optional Text field behavior helper for clear button + live counter,
  if later evidence shows it should be a reusable runtime helper.
```

Neither candidate is required for v3.5.7 Phase 1 entry.

---

## §12 — Phase 1 entry conditions

Phase 1 may begin after this report is reviewed and approved.

Expected Phase 1 outputs:

```txt
products/reference-implementations/axismundi-lab/modules/text-field/docs/
  TEXT-FIELD-SPEC-AUDIT.md
  TEXT-FIELD-MEASUREMENT-AUDIT.md
  TEXT-FIELD-WP-MAPPING.md
```

Phase 1 must not create:

```txt
lab-text-field.css
lab-text-field-pattern.html
lab-text-field.js
```

Those are Phase 2 topics, and `lab-text-field.js` is not currently approved.

Phase 1 must include:

```txt
- 3-doc trio, not 4-doc runtime shape.
- G1-G16 applicability discussion.
- Native markup contract.
- Clear button static-only decision.
- Counter static-only decision.
- Textarea-as-variant decision.
- Date/Time boundary.
- WordPress theme-can / plugin-should mapping.
- Playwright Phase 2/3 QA requirements.
```

---

## §13 — G1-G16 applicability

Text field is the first Wave 1 row that needs both Component Full-Spec and Interaction gate coverage.

### §13.1 — G1-G10 Component Full-Spec gates

| Gate | Applicability | Phase 0 disposition |
|---|---|---|
| G1 validator | Applies | Must remain 1.000 PASS after docs |
| G2 baseline untouched | Applies | Phase 0 preserves baseline |
| G3 publish surface | Applies when publish changes | Not Phase 0/1 unless publish is touched |
| G4 module artifacts | Applies | Phase 2 will create artifacts |
| G5 CHANGELOG | Applies | Phase 5 |
| G6 Static Visual QA | Applies | Phase 3 with Playwright pre-filter + user verdict |
| G7 Principle 1 | Applies | Real input/textarea/button controls required |
| G8 Principle 2 | Applies | Native HTML semantics required |
| G9 WCAG/native accuracy | Applies | Phase 1 MEASUREMENT/WP-MAPPING |
| G10 3-doc audit pattern | Applies | Phase 1 creates SPEC/MEASUREMENT/WP-MAPPING |

### §13.2 — G11-G16 Interaction gates

Interaction gates apply because the matrix category includes Interaction, but they apply differently from runtime modules.

| Interaction area | Phase 0 disposition |
|---|---|
| Role/state contract | Native input/textarea/label; no custom textbox role |
| Keyboard path | Browser-native input/textarea keyboard behavior |
| Focus behavior | Active indicator/floating label; no focus trap |
| Progressive enhancement | Base field works without JS |
| Runtime extraction | Not required unless future clear/counter API is approved |
| Inventory accuracy | Strongly applies; DOM order and class-name precision are critical |

Decision:

```txt
G11-G16 should be discussed in TEXT-FIELD-SPEC-AUDIT.md, but v3.5.7 does
not create a separate Interaction Runtime audit doc unless Phase 1 discovers
an actual JS runtime contract.
```

---

## §14 — Non-goals

Not in v3.5.7 Phase 0:

```txt
- Create lab/modules/text-field/.
- Create audit docs.
- Create lab-text-field.css.
- Create lab-text-field-pattern.html.
- Create lab-text-field.js.
- Modify components.css §9.
- Modify components.css §0.
- Modify style-guide.html.
- Modify tokens.css.
- Modify blocks.css.
- Modify theme.json.
- Modify CURRENT-STATE.md.
- Modify NEXT-SESSION.md.
- Modify CHANGELOG.md.
- Modify ROADMAP.md.
- Modify BACKLOG.md.
- Implement clear button click behavior.
- Implement live counter behavior.
- Implement custom validation.
- Implement Date picker or Time picker.
- Implement Search bar behavior.
- Implement dropdown/select/popover behavior.
- Import Material Web.
- Add <md-filled-text-field> or <md-outlined-text-field>.
- Add ripple to the field host.
```

---

## §15 — Verdict

Phase 0 verdict:

```txt
PASS — Text field #16 is ready for Phase 1 planning/execution after review.
```

Settled decisions:

```txt
1. Audit doc shape:
   3-doc Component Full-Spec trio. No fourth interaction doc in v3.5.7.

2. Clear button:
   Static visual/CSS visibility only. Click clear behavior is integration.

3. Counter:
   Static visual display only. Live counter is integration.

4. Native validation:
   Manual .is-error and native :user-invalid are in scope.
   Custom validation messages and persistence are plugin/theme/editor territory.

5. Textarea:
   Text field variant, not a separate matrix row.

6. Date/Time boundary:
   Text field owns generic input shell only.
   Date/Time owns picker behavior.

7. WordPress form boundary:
   Theme can style native controls and static patterns.
   Plugin/editor should own schema, validation, submission, persistence.
```

Next allowed action:

```txt
Codex plan-first or execution-lane agent prepares Phase 1 audit docs:
  TEXT-FIELD-SPEC-AUDIT.md
  TEXT-FIELD-MEASUREMENT-AUDIT.md
  TEXT-FIELD-WP-MAPPING.md

No implementation artifacts before Phase 1 approval.
```

