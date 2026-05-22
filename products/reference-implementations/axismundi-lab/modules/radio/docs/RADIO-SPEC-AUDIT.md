# Radio - Spec Audit (v3.6.10 Phase 2)

> **Component**: Radio #19
> **Category**: Component Full-Spec
> **Status**: Phase 2 implementation candidate
> **Companions**: `RADIO-MEASUREMENT-AUDIT.md`, `RADIO-WP-MAPPING.md`

## Scope

```txt
In scope:
  - Native input[type="radio"]
  - Same-name single-select group
  - Fieldset / legend context
  - Unchecked / checked / disabled
  - Label click and browser keyboard behavior
  - Form value participation

Out of scope:
  - Indeterminate state
  - Error visual state
  - Custom JS runtime
  - Provider runtime
```

## Baseline Contract

Baseline authority remains `components.css §23`:

```txt
.ax-radio
.ax-radio__input
.ax-radio__visual
.ax-radio__label
```

## Error-State Boundary

The baseline does not expose a Radio error selector in v3.6.10. This module
does not invent one. A future error-state request should route to a baseline
update cycle.

## State Matrix

| State | Route |
|---|---|
| unchecked | native input |
| checked | native input |
| disabled | native input |
| focus-visible | existing baseline |
| active/pressed | existing baseline |

## Verdict

Radio can proceed as a provider-free and JS-free Full-Spec lab module.
