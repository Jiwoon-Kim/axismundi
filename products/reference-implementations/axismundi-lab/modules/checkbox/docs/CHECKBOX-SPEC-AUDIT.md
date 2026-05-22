# Checkbox - Spec Audit (v3.6.10 Phase 2)

> **Component**: Checkbox #18
> **Category**: Component Full-Spec
> **Status**: Phase 2 implementation candidate
> **Companions**: `CHECKBOX-MEASUREMENT-AUDIT.md`, `CHECKBOX-WP-MAPPING.md`

## Scope

```txt
In scope:
  - Native input[type="checkbox"]
  - Unchecked / checked / indeterminate
  - Disabled unchecked / checked / indeterminate
  - Error visual state, because baseline exposes .ax-checkbox.is-error
  - Label click and Space key native toggle
  - Form value participation

Out of scope:
  - Provider runtime
  - WordPress editor binding
  - Form submission persistence
  - Radio/Switch indeterminate mapping
```

## Baseline Contract

Baseline authority remains `components.css §22`:

```txt
.ax-checkbox
.ax-checkbox__input
.ax-checkbox__visual
.ax-checkbox__check
.ax-checkbox__label
```

The lab module adds only validation-page layout in `lab-checkbox.css`.

## Runtime Boundary

`lab-checkbox.js` is fixture setup only. It sets
`HTMLInputElement.indeterminate` for inputs marked
`data-checkbox-indeterminate` because HTML has no indeterminate attribute.

It is not a component runtime:

```txt
No provider API.
No persistent document/window listener.
No custom toggle behavior.
Native checkbox behavior remains browser-owned.
```

## State Matrix

| State | Route |
|---|---|
| unchecked | native input |
| checked | native input |
| indeterminate | fixture setup property |
| disabled | native input |
| error | existing `.ax-checkbox.is-error` baseline |
| focus-visible | existing baseline |
| active/pressed | existing baseline |

## Verdict

Checkbox can proceed as a provider-free Full-Spec lab module.
