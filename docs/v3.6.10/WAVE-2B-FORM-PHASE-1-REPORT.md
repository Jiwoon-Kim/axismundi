# v3.6.10 - Wave 2B Form - Phase 1 Diagnostic Inventory

Date: 2026-05-22

Phase: 1 - Diagnostic Inventory

## Verdict

Recommended route:

```txt
Route B - Form Controls Core First
```

Implement Checkbox #18, Radio #19, and Switch #20 as the v3.6.10 Wave 2B-1
slice, after review approval. Do not include Dialog, Sheet, Date+Time,
Split button, FAB menu, or Toolbar in Phase 2.

Reason:

- Checkbox / Radio / Switch are the only remaining Wave 2B candidates that
  are all Inputs, all TODO, all independent, and all have baseline primitives
  plus styleguide anchors.
- They exercise the first true input-control domain after five successful
  diagnostic-first cycles.
- They do not require provider edits to `popover/`, `ripple/`, or
  `icon-system/`.
- They avoid reopening BACKLOG #47, #46, #41, #44, #21, or Date+Time PARTIAL
  work.

Implementation files remain untouched in Phase 1.

## Current Local State

Phase 1 entry status:

```txt
## main...origin/main [ahead 21]
```

No unstaged or untracked implementation work was present before writing this
report.

Baseline:

```txt
origin/main:     2baecbb
local HEAD:      98435c0
last close:      v3.6.9 Wave 2A-2 Menu / Popover Consumer
Phase 0 plan:    docs/v3.6.10/WAVE-2B-FORM-PHASE-0-PLAN.md
WordPress core:  7.0 per v3.6.9 close evidence
```

## Source Inputs

This report uses the Phase 0 reading set from
`docs/v3.6.10/WAVE-2B-FORM-PHASE-0-PLAN.md`, which enumerates all 47 entries
from `NEXT-SESSION.md §0`.

Focused Phase 1 reads:

```txt
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/
products/reference-implementations/axismundi-lab/modules/date-time/docs/DATE-TIME-AUDIT.md
products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js
```

## Phase 0 Review Notes Absorbed

### P3-1 - components.css Section Disambiguation

Phase 0 used formal-looking labels for later baseline areas:

```txt
§31 FAB menu
§32 Split button
§33 Date picker
§34 Time picker
```

Corrected Phase 1 mapping:

```txt
Dialog          formal §12, selector range around lines 1884-2002
Sheet           formal §13, selector range around lines 2034-2134
Checkbox        formal §22, selector range 3364-3549
Radio           formal §23, selector range 3562-3695
Switch          formal §24, selector range 3732-3950
Toolbar         formal §29, selector range 5020-5077
FAB menu        Chunk H1, selector range 5287-5522
Split button    Chunk H2, selector range 5547-5607
Date picker     Chunk H3, selector range 5651-5818
Time picker     Chunk H4, selector range 5873-6166
```

The selectors exist. The correction is about section labels, not about missing
baseline coverage.

### P3-2 - Dialog / Sheet Selector Convention

Dialog and Sheet baseline selectors use bare prefixes:

```txt
.dialog
.dialog--basic
.dialog--full-screen
.sheet
.sheet--bottom-modal
.sheet--side-modal
```

They do not use `.ax-dialog` or `.ax-sheet`. If a future route selects Dialog
or Sheet, lab-scoped selectors should target `.lab-dialog-demo .dialog` and
`.lab-sheet-demo .sheet`, not invented `.ax-*` names.

v3.6.10 Route B does not implement Dialog or Sheet.

### P3-3 - Date+Time Combined Candidate

Date picker #22 and Time picker #23 are a combined candidate:

```txt
Date+Time picker #22+#23
existing module: products/reference-implementations/axismundi-lab/modules/date-time/
status: PARTIAL
```

The existing module has:

```txt
lab-date-time.css
lab-date-time.js
lab-date-time-pattern.html
docs/DATE-TIME-AUDIT.md
```

The audit records a v3.4.7 extraction with inherited a11y gaps and BACKLOG #19
for the WAI-ARIA Date Picker grid pattern. This is a separate PARTIAL
completion cycle, not part of v3.6.10 Route B.

### P3-4 - Indeterminate Scope

Indeterminate applies only to Checkbox:

```txt
Checkbox:  indeterminate applies through HTMLInputElement.indeterminate
Radio:     no indeterminate state
Switch:    no indeterminate state
```

Phase 2 should not fake indeterminate for Radio or Switch.

### P3-5 - Lock 5 Framing

v3.6.10 would be the sixth diagnostic-first cycle if it closes cleanly, and
the first input-domain cycle. Phase 5 must make an explicit promote-or-defer
decision with six-cycle evidence.

Phase 1 recommendation remains:

```txt
Do not promote Lock 5 in Phase 1.
Keep AGENTS.md and CLAUDE.md unchanged.
Reconsider in Phase 5 after seeing Phase 2/3 evidence.
```

## Module Presence Inventory

Existing relevant modules:

```txt
button/
button-group/
date-time/
fab/
menu/
popover/
ripple/
text-field/
search-bar/
icon-system/
```

Missing target modules:

```txt
checkbox/
radio/
switch/
dialog/
sheet/
split-button/
toolbar/
fab-menu/
```

Implication:

```txt
Checkbox / Radio / Switch can be added cleanly as new modules.
Dialog / Sheet can also be added cleanly, but runtime scope is larger.
Split button / Toolbar / FAB menu can be added cleanly, but they are Actions
and provider/composition-heavy.
Date+Time already exists and should be treated as PARTIAL completion.
```

## Canonical Row Classification

| Row | Component | Group | Category | Status | Existing | Target | Provider |
|---:|---|---|---|---|---|---|---|
| 5 | FAB menu | Actions | Full-Spec + Interaction | TODO | - | `fab-menu/` | `popover/`, `ripple/`, `icon-system/` |
| 7 | Split button | Actions | Full-Spec + Interaction | TODO | - | `split-button/` | `popover/`, `ripple/` |
| 8 | Toolbar | Actions | Full-Spec | TODO | - | `toolbar/` | `ripple/` |
| 18 | Checkbox | Inputs | Full-Spec | TODO | - | `checkbox/` | - |
| 19 | Radio | Inputs | Full-Spec | TODO | - | `radio/` | - |
| 20 | Switch | Inputs | Full-Spec | TODO | - | `switch/` | - |
| 22+#23 | Date+Time picker | Inputs | Full-Spec + Interaction | PARTIAL | `date-time/` | `date-time/` | `popover/` |
| 26 | Dialog | Feedback | Interaction Runtime | TODO | - | `dialog/` | - |
| 27 | Sheet | Feedback | Interaction Runtime | TODO | - | `sheet/` | - |

The three Route B rows are the only rows in this table with:

```txt
Group = Inputs
Category = Component Full-Spec
Status = TODO
Provider = none
Existing module = none
```

## Baseline CSS Inventory

### Checkbox

Evidence:

```txt
Chunk F1 - Checkbox + Radio
formal §22 Checkbox
selector count: 29
range: lines 3364-3549
```

Key selector families:

```txt
.ax-checkbox
.ax-checkbox__input
.ax-checkbox__visual
.ax-checkbox__check
.ax-checkbox__label
.ax-checkbox.is-error
:hover
:focus-visible
:active
:checked
:indeterminate
:disabled
```

State coverage present in baseline:

```txt
unchecked
checked
indeterminate
hover
focus-visible
pressed/active
error
disabled
disabled checked
disabled indeterminate
label disabled
```

### Radio

Evidence:

```txt
Chunk F1 - Checkbox + Radio
formal §23 Radio button
selector count: 20
range: lines 3562-3695
```

Key selector families:

```txt
.ax-radio
.ax-radio__input
.ax-radio__visual
.ax-radio__label
:hover
:focus-visible
:active
:checked
:disabled
```

State coverage present in baseline:

```txt
unchecked
checked
hover
focus-visible
pressed/active
disabled
disabled checked
label disabled
```

No indeterminate state.

### Switch

Evidence:

```txt
Chunk F2 - Switch + Slider
formal §24 Switch
selector count: 22
range: lines 3732-3950
```

Key selector families:

```txt
.ax-switch
.ax-switch__input
.ax-switch__track
.ax-switch__label
:hover
:focus-visible
:active
:checked
:disabled
```

State coverage present in baseline:

```txt
off
on
hover
focus-visible
pressed/active
disabled
disabled checked
label disabled
```

No indeterminate state.

### Dialog

Evidence:

```txt
formal §12 Dialog
bare selector convention
selector range: lines 1884-2002
```

Key selector families:

```txt
.dialog
.dialog::backdrop
.dialog--basic
.dialog--full-screen
.dialog__icon
.dialog__headline
.dialog__supporting
.dialog__actions
.dialog__app-bar
.dialog__body
```

Phase 1 classification:

```txt
Interaction Runtime candidate.
Do not include in Route B.
Needs a dedicated runtime cycle or split decision.
```

### Sheet

Evidence:

```txt
formal §13 Sheet
bare selector convention
selector range: lines 2034-2134
```

Key selector families:

```txt
.sheet
.sheet--bottom-modal
.sheet--side-modal
.sheet__handle
.sheet__header
.sheet__title
.sheet__body
```

Phase 1 classification:

```txt
Interaction Runtime candidate.
Do not include in Route B.
Pair with Dialog only after a runtime boundary review.
```

### Date+Time

Evidence:

```txt
Date picker Chunk H3, selector range 5651-5818
Time picker Chunk H4, selector range 5873-6166
existing module: date-time/
existing JS: lab-date-time.js
audit: docs/DATE-TIME-AUDIT.md
```

Phase 1 classification:

```txt
PARTIAL completion candidate.
Do not include in v3.6.10 Route B.
```

Reasons:

- already has extracted runtime;
- audit records inherited a11y gaps;
- BACKLOG #19 grid navigation is a separate concern;
- provider row says Date+Time consumes `popover/`;
- completion would be materially different from native input controls.

### Split Button

Evidence:

```txt
Chunk H2, selector range 5547-5607
canonical row #7
provider: popover/, ripple/
```

Phase 1 classification:

```txt
Actions consumer candidate.
Do not include in Route B.
```

### FAB Menu

Evidence:

```txt
Chunk H1, selector range 5287-5522
canonical row #5
provider: popover/, ripple/, icon-system/
depends on closed FAB family + Menu module
```

Phase 1 classification:

```txt
Actions consumer candidate.
Do not include in Route B.
```

### Toolbar

Evidence:

```txt
formal §29 Toolbar
selector range 5020-5077
canonical row #8
provider: ripple/
```

Phase 1 classification:

```txt
Actions full-spec candidate.
Do not include in Route B.
```

## Styleguide Inventory

Every Wave 2B candidate has a baseline catalog anchor and a table-of-contents
link.

| Anchor | Count | Lines |
|---|---:|---|
| `components-checkbox` | 2 | 704, 2191 |
| `components-radio` | 2 | 705, 2291 |
| `components-switch` | 2 | 706, 2343 |
| `components-date-picker` | 2 | 708, 2431 |
| `components-time-picker` | 2 | 709, 2533 |
| `components-dialog` | 2 | 716, 2754 |
| `components-sheet` | 2 | 717, 2808 |
| `components-fab-menu` | 2 | 684, 1092 |
| `components-split-button` | 2 | 686, 1330 |
| `components-toolbar` | 2 | 687, 1385 |

Baseline specimen text/class occurrence counts:

| Pattern | Count |
|---|---:|
| `ax-checkbox` | 40 |
| `ax-radio` | 24 |
| `ax-switch` | 20 |
| `dialog` | 43 |
| `sheet` | 46 |
| `ax-date-picker` | 50 |
| `ax-time-picker` | 20 |
| `ax-fab-menu` | 65 |
| `ax-split-button` | 10 |
| `ax-toolbar` | 5 |

Interpretation:

```txt
style-guide.html proves baseline specimens exist.
It does not prove module DONE status.
```

## Native Semantics Map

### Checkbox #18

Required Phase 2/3 semantics:

```txt
native <input type="checkbox">
label association
unchecked
checked
indeterminate
disabled unchecked
disabled checked
disabled indeterminate
error visual state
focus-visible
keyboard Space toggles
form value participation
```

Indeterminate note:

```txt
HTML cannot set indeterminate as an attribute.
Phase 2 may add a tiny lab-checkbox.js whose only job is pattern-fixture
setup for demo indeterminate inputs. This is not a component interaction
runtime and must not become a provider or a global behavior layer.
```

If review does not want any JS in Route B, Phase 2 must instead record
indeterminate as CSS-covered but not live-demonstrated, which is weaker.

### Radio #19

Required Phase 2/3 semantics:

```txt
native <input type="radio">
shared name group
fieldset / legend or equivalent label context
unchecked
checked
disabled
focus-visible
keyboard arrow behavior supplied by browser where grouped
keyboard Space selection
form value participation
```

No indeterminate state.

### Switch #20

Required Phase 2/3 semantics:

```txt
native <input type="checkbox">
role=switch only if it does not break native form behavior
label association
off
on
disabled off
disabled on
focus-visible
keyboard Space toggles
form value participation
```

No indeterminate state.

## Runtime / Provider Dependency Map

### Route B Form Trio

```txt
Checkbox: independent, no provider
Radio:    independent, no provider
Switch:   independent, no provider
```

Route B should not load `popover/`, `ripple/`, or `icon-system/` unless a
Phase 2 pattern uses an already-closed composed child component that requires
one. The core form controls themselves do not require these providers.

### Deferred Candidates

```txt
Date+Time #22+#23:  date-time/ PARTIAL, consumes popover/, existing JS
Dialog #26:         runtime candidate, focus/backdrop/Escape
Sheet #27:          runtime candidate, modal surface behavior
Split button #7:    popover/ + ripple/ consumer
FAB menu #5:        popover/ + ripple/ + icon-system consumer, depends on FAB + Menu
Toolbar #8:         ripple/ consumer
```

Provider fences remain intact.

## Route Selection

### Selected - Route B

Route B should proceed to Phase 2 after review:

```txt
Checkbox / Radio / Switch core form controls first.
```

Expected Phase 2 write scope:

```txt
products/reference-implementations/axismundi-lab/modules/checkbox/lab-checkbox.css
products/reference-implementations/axismundi-lab/modules/checkbox/lab-checkbox-pattern.html
products/reference-implementations/axismundi-lab/modules/checkbox/lab-checkbox.js
products/reference-implementations/axismundi-lab/modules/checkbox/docs/CHECKBOX-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/checkbox/docs/CHECKBOX-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/checkbox/docs/CHECKBOX-WP-MAPPING.md
products/reference-implementations/axismundi-lab/modules/radio/lab-radio.css
products/reference-implementations/axismundi-lab/modules/radio/lab-radio-pattern.html
products/reference-implementations/axismundi-lab/modules/radio/docs/RADIO-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/radio/docs/RADIO-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/radio/docs/RADIO-WP-MAPPING.md
products/reference-implementations/axismundi-lab/modules/switch/lab-switch.css
products/reference-implementations/axismundi-lab/modules/switch/lab-switch-pattern.html
products/reference-implementations/axismundi-lab/modules/switch/docs/SWITCH-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/switch/docs/SWITCH-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/switch/docs/SWITCH-WP-MAPPING.md
docs/v3.6.10/WAVE-2B-FORM-PHASE-2-REPORT.md
```

`lab-checkbox.js` is included only because native indeterminate requires a
property assignment for a live demo. It must be small, local, and documented as
fixture setup rather than component runtime.

Route B constraints:

- lab-scoped CSS only;
- no `components.css` changes;
- no provider changes;
- no WordPress/Pilot changes;
- no form submission or persistence;
- no plugin/editor integration;
- no fake controls replacing native semantics.

### Rejected - Route A Full Wave 2B

Rejected for v3.6.10.

Reason:

```txt
Full Wave 2B combines independent form controls, Interaction Runtime surfaces,
Actions popover consumers, and Date+Time PARTIAL completion. That is too broad
for one safe implementation cycle.
```

### Rejected - Route C Dialog / Sheet Runtime First

Rejected for v3.6.10.

Reason:

```txt
Dialog and Sheet require runtime decisions around focus trap, Escape/backdrop,
modal visibility, and possibly portal/overlay conventions. They deserve a
dedicated cycle after input-domain proof.
```

### Rejected - Route D Actions Consumer First

Rejected for v3.6.10.

Reason:

```txt
Split button and FAB menu are popover consumers and may interact with #47
provider hygiene. Toolbar is Actions/ripple territory. These are coherent, but
less domain-diverse than the input family after Navigation.
```

### Rejected - Route E Audit / Split Decision Only

Rejected as Phase 2 outcome.

Reason:

```txt
Phase 1 produced a bounded implementation route with provider-neutral
constraints. No-code split-only would be unnecessary defer.
```

However, Route E's structure is accepted as future routing:

```txt
Wave 2B-1: Checkbox / Radio / Switch - selected for v3.6.10
Wave 2B-2: Dialog / Sheet - future
Wave 2B-3: Date+Time #22+#23 PARTIAL completion - future
Wave 2B-4: Actions consumers: Split button / FAB menu / Toolbar - future
```

### Rejected - Route F Other

No evidence required an alternate route.

## Phase 2 Constraints

Allowed selectors:

```txt
.lab-checkbox-demo ...
.lab-radio-demo ...
.lab-switch-demo ...
```

Allowed baseline consumer classes inside pattern markup:

```txt
.ax-checkbox
.ax-checkbox__input
.ax-checkbox__visual
.ax-checkbox__check
.ax-checkbox__label
.ax-radio
.ax-radio__input
.ax-radio__visual
.ax-radio__label
.ax-switch
.ax-switch__input
.ax-switch__track
.ax-switch__label
```

Forbidden selector shapes:

```txt
unscoped .ax-checkbox overrides
unscoped .ax-radio overrides
unscoped .ax-switch overrides
unscoped .dialog overrides
unscoped .sheet overrides
unscoped [data-ax-ripple] overrides
unscoped .material-symbols-rounded overrides
provider-specific popover/ripple/icon-system branches
```

Runtime constraints:

```txt
No lab-radio.js unless Phase 2 finds an unexpected blocker and stops.
No lab-switch.js unless Phase 2 finds an unexpected blocker and stops.
lab-checkbox.js may only set native indeterminate state for fixture examples.
No document-level or window-level persistent listeners.
No provider modules.
```

## Phase 3 Expected QA For Route B

For each module page:

```txt
desktop light
desktop dark
mobile light at about 390px
mobile dark at about 390px
console errors: 0
horizontal overflow: 0
```

Checkbox:

```txt
unchecked visible
checked visible
indeterminate visible
disabled unchecked visible
disabled checked visible
disabled indeterminate visible
error visible
focus-visible visible
Space toggles enabled input
disabled does not toggle
label click toggles associated input
```

Radio:

```txt
unchecked visible
checked visible
disabled visible
fieldset / legend or group label present
same-name group has one selected value
keyboard selection works
label click selects associated input
disabled does not select
```

Switch:

```txt
off visible
on visible
disabled off visible
disabled on visible
focus-visible visible
Space toggles enabled switch
label click toggles associated input
disabled does not toggle
```

## Files Still Not Expected To Change In Phase 2

```txt
AGENTS.md
CLAUDE.md
theme.json
functions.php
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/popover/*
products/reference-implementations/axismundi-lab/modules/ripple/*
products/reference-implementations/axismundi-lab/modules/icon-system/*
products/reference-implementations/axismundi-lab/modules/app-bar/*
products/reference-implementations/axismundi-lab/modules/nav-bar/*
products/reference-implementations/axismundi-lab/modules/nav-rail/*
products/reference-implementations/axismundi-lab/modules/tabs/*
products/reference-implementations/axismundi-lab/modules/menu/*
products/reference-implementations/axismundi-lab/modules/date-time/*
products/reference-implementations/axismundi-lab/modules/text-field/*
products/reference-implementations/axismundi-lab/modules/search-bar/*
products/reference-implementations/axismundi-lab/modules/button/*
products/reference-implementations/axismundi-lab/modules/button-group/*
products/reference-implementations/axismundi-lab/modules/fab/*
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/fixtures/*
tools/validators/validate_theme_pilot.py
tools/validators/validate_pilot_specimen_wall.js
tools/generators/build_pilot_specimen_wall.py
```

If Phase 2 finds that any of these must change, stop and return to Phase 1
review.

## Lock Status

### Lock 1

Preserved. No WordPress theme or `wp-custom` source changes are needed.

### Lock 2

Preserved. No token or md-sys/md-ref map changes are needed.

### Lock 3

Preserved. `core/button` semantic routing is unrelated and not reopened.

### Lock 4

Preserved. Dialog/Sheet and popover consumer candidates are explicitly routed
out of v3.6.10 Route B rather than collapsed into a generic visual patch.

### Lock 5

Not promoted in Phase 1. Keep as methodology finding.

Phase 5 must make an explicit promote-or-defer decision using six-cycle
evidence if v3.6.10 closes cleanly.

## Validation Performed

Phase 1 is read-only diagnostic work.

Commands / checks:

```txt
git status --short --branch
Get-ChildItem modules
Select-String components.css for candidate selector families
Select-String style-guide.html for candidate anchors and specimens
Read MODULE-STATUS-MATRIX rows #5, #7, #8, #18, #19, #20, #22, #23, #26, #27
Read DATE-TIME-AUDIT.md and lab-date-time.js for PARTIAL status context
```

No validation command generated tracked artifacts.

## Next

Submit this Phase 1 report for review.

If approved, Phase 2 should implement Route B:

```txt
Checkbox / Radio / Switch core form controls.
```

Do not edit providers, baseline CSS, WordPress/Pilot files, closed Wave 2A
modules, or lock files.
