# Text Field — WordPress Mapping Audit (v3.5.7 Phase 1)

> **Status**: Phase 1 WordPress/form mapping body authored. Implementation not started.  
> **Component**: Text field #16  
> **Category**: Component Full-Spec + Interaction  
> **Companions**: `TEXT-FIELD-SPEC-AUDIT.md`, `TEXT-FIELD-MEASUREMENT-AUDIT.md`

---

## §0 — Status / Scope

This mapping audit formalizes the Phase 0 WordPress/form boundary for Text field.

Phase 1 is documentation-only:

```txt
No lab-text-field.css.
No lab-text-field-pattern.html.
No lab-text-field.js.
No WordPress block registration.
No baseline edits.
```

Core decision:

```txt
Theme can style native form controls and static field patterns.
Plugin/editor should own schema, validation logic, submission, persistence,
and data-bound behavior.
```

---

## §1 — Inputs Read

Cycle docs:

```txt
docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md
docs/v3.5.7/TEXT-FIELD-PHASE-1-PLAN.md
```

Baseline:

```txt
components.css §9 Text field
style-guide.html #components-text-field
```

Companions:

```txt
TEXT-FIELD-SPEC-AUDIT.md
TEXT-FIELD-MEASUREMENT-AUDIT.md
```

Precedents:

```txt
Button v3.5.1 WP mapping
Icon button v3.5.2 accessible-name strictness
Card v3.5.3 anti-pattern and theme-can/plugin-should structure
FAB v3.5.5 static behavior deferral
```

---

## §2 — WordPress Surface Inventory

Likely WordPress contexts:

| Surface | Text field relevance | Phase 1 classification |
|---|---|---|
| `core/post-comments-form` | comment textarea, author/name/email/url fields | Primary mapping context |
| `core/search` | search input, but Search bar #17 owns search-specific behavior | Sibling boundary |
| `core/html` | static author-provided field markup | Possible composition path |
| pattern composition | theme-authored forms/templates | Theme-can |
| custom block | schema-driven custom fields | Plugin/editor territory |
| form plugin | submissions, validation, persistence | Plugin territory |

Text field is not a generic WordPress form system. It is a visual/semantic primitive that WordPress forms may consume.

---

## §3 — core/post-comments-form Mapping

Relevant controls:

```txt
comment textarea
author name input
email input
URL input
```

Theme-can:

```txt
wrap or render native fields with .text-field markup where theme templates
control output
align labels, supporting text, error states, and textarea layout
```

Plugin/core-owned:

```txt
comment submission handling
required-field logic
moderation flow
server-side validation
error messages from WordPress
```

Mapping requirement:

```txt
Do not hide WordPress semantics behind purely decorative wrappers.
Keep native input/textarea/label relationships intact.
```

---

## §4 — core/search Sibling Boundary

Search-related inputs are adjacent, not automatically Text field.

Classification:

```txt
Generic search input shell:
  may use Text field visual primitive where appropriate.

Search bar #17:
  owns search-specific surface, leading search icon, trailing voice/avatar
  affordances, expansion behavior, and search-expansion/ PARTIAL legacy.
```

Forbidden:

```txt
Absorb Search bar #17 into Text field #16.
Treat search expansion as Text field interaction.
```

---

## §5 — core/html / Pattern Composition Path

`core/html` can contain static field markup, but it is not a structured component integration.

Use:

```txt
documentation examples
static prototypes
theme patterns
```

Do not use it as:

```txt
schema system
validation system
submission system
dynamic field editor
```

Pattern composition path:

```txt
Theme supplies text-field markup in templates or patterns.
Plugin/editor supplies data and behavior when the field is not static.
```

---

## §6 — Theme-Can / Plugin-Should Boundary

### §6.1 — Theme can

Theme can:

```txt
style native input/textarea controls
ship static Text field patterns
map labels/helper/counter markup
apply visual states in server-rendered output
provide CSS for filled/outlined variants
provide icon slot placement
```

### §6.2 — Plugin/editor should

Plugin/editor should:

```txt
own field schema
own validation rules
own dynamic validation messages
own form submission
own persistence
own live counter behavior
own clear-click behavior when it mutates data
own block editor controls
```

Boundary statement:

```txt
Text field gives WordPress a safe visual/semantic field primitive. It does
not become the app's form engine.
```

---

## §7 — Field Schema / Validation / Persistence Boundary

### §7.1 — Native validation

Theme/static markup can use:

```html
<input type="email" required>
<input pattern="[0-9]*" inputmode="numeric">
<textarea maxlength="280"></textarea>
```

Text field CSS can reflect:

```txt
:user-invalid
.is-error
```

### §7.2 — Custom validation

Plugin/editor owns:

```txt
message generation
async validation
server validation
schema constraints
translation/localization of validation messages
submission blocking
```

### §7.3 — Persistence

Out of Text field scope:

```txt
save form data
sync to post meta/user meta/options
federate field values
submit comments
send emails
```

---

## §8 — Date/Time And Search Composition Boundary

Date picker #22 and Time picker #23 may compose Text field shells.

Text field owns:

```txt
input shell
label
slots
supporting/counter row
native validation display
```

Date/Time owns:

```txt
calendar / dial / picker UI
popover/dialog/docked surfaces
date/time parsing
selection state
```

Search bar #17 owns:

```txt
search-specific leading/trailing affordances
active search view
search-expansion legacy absorption
```

---

## §9 — Accessible Form Contract

Required authoring pattern:

```html
<div class="text-field text-field--outlined">
  <div class="text-field__container">
    <input class="text-field__input"
           id="comment-email"
           name="email"
           type="email"
           placeholder=" "
           aria-describedby="comment-email-help">
    <label class="text-field__label" for="comment-email">Email</label>
  </div>
  <div class="text-field__bottom">
    <span class="text-field__supporting" id="comment-email-help">
      We will not publish your email.
    </span>
  </div>
</div>
```

Required:

```txt
real id/for label association
native input/textarea
aria-describedby for helper/error text when semantically connected
button accessible names for trailing actions
```

Forbidden:

```txt
placeholder-only labels
role=textbox wrapper controls
clear icon without button semantics
aria-disabled replacing native disabled
```

---

## §10 — Anti-pattern Inventory

| Anti-pattern | Why it fails | Correct direction |
|---|---|---|
| Placeholder-only label | Loses persistent accessible/visual label | Use `<label for>` |
| `.text-field__container` as `<label>` containing button | Invalid/problematic button-in-label pattern | Keep container div + separate label |
| `<div role="textbox">` | Reimplements native input badly | Use input/textarea |
| Clear icon without accessible name | Icon-only control inaccessible | Use button + aria-label |
| Counter without maxlength/source | Counter has no truth source | Pair with maxlength or integration logic |
| Theme CSS owns custom validation | Theme becomes form engine | Plugin/editor owns validation |
| Date picker inside generic Text field | Absorbs row #22/#23 behavior | Compose with Date/Time modules |
| Search expansion inside Text field | Absorbs row #17 | Keep Search bar separate |
| Inline SVG where icon-system should apply | Bypasses icon-system pattern | Use Material Symbols/icon-system pattern |
| Arbitrary core input styling without wrapper | Misses Axismundi markup contract | Use `.text-field` wrapper contract |

---

## §11 — ActivityPub / Social CMS Notes

Text field may appear in social CMS surfaces:

```txt
status composer title/body fields
profile fields
comment/reply forms
settings forms
moderation notes
```

Boundary:

```txt
Text field owns the visual/native input primitive.
Social CMS/plugin layer owns persistence, federation, validation, privacy,
rate limits, and submission behavior.
```

No ActivityPub runtime work belongs to v3.5.7 Text field Phase 1.

---

## §12 — Verdict

WP-MAPPING Phase 1 verdict:

| Criterion | Phase 1 status | Notes |
|---|---|---|
| WordPress surface inventory | PASS | Primary contexts enumerated |
| core/post-comments-form mapping | PASS | Form context documented |
| Search/Date/Time boundaries | PASS | Sibling rows protected |
| Theme-can/plugin-should split | PASS | Explicit |
| Accessible form contract | PASS | id/for/describedby/button names |
| Anti-pattern inventory | PASS | 10 anti-patterns recorded |

Phase 1 verdict:

```txt
PASS at Phase 1 level.
```

---

## §13 — Cross-References

Companions:

```txt
TEXT-FIELD-SPEC-AUDIT.md
TEXT-FIELD-MEASUREMENT-AUDIT.md
```

Cycle docs:

```txt
docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md
docs/v3.5.7/TEXT-FIELD-PHASE-1-PLAN.md
```

Precedents:

```txt
Button v3.5.1 WP mapping
Icon button v3.5.2 accessible-name contract
Card v3.5.3 WP bridge / anti-pattern inventory
FAB v3.5.5 static behavior deferral
```

