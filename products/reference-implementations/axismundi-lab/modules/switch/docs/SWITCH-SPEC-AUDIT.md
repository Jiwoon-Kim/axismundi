# Switch - Spec Audit (v3.6.10 Phase 2)

> **Component**: Switch #20
> **Category**: Component Full-Spec
> **Status**: Phase 2 implementation candidate
> **Companions**: `SWITCH-MEASUREMENT-AUDIT.md`, `SWITCH-WP-MAPPING.md`

## Scope

```txt
In scope:
  - Native input[type="checkbox"]
  - role="switch"
  - Off / on
  - Disabled off / on
  - Label click and Space key native toggle
  - Form value participation

Out of scope:
  - Indeterminate state
  - Error visual state
  - Custom JS runtime
  - Provider runtime
```

## Native / ARIA Boundary

The implementation uses:

```html
<input class="ax-switch__input" type="checkbox" role="switch">
```

This keeps native checkbox form behavior while exposing switch semantics to
assistive technology. Phase 3 must verify that Space toggles and FormData
participation still work.

## Baseline Contract

Baseline authority remains `components.css §24`:

```txt
.ax-switch
.ax-switch__input
.ax-switch__track
.ax-switch__label
```

## Error-State Boundary

The baseline does not expose a Switch error selector in v3.6.10. This module
does not invent one. A future error-state request should route to a baseline
update cycle.

## Verdict

Switch can proceed as a provider-free and JS-free Full-Spec lab module.
