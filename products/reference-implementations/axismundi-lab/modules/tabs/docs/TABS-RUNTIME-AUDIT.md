# Tabs Runtime Audit

Status: v3.6.8 Phase 2 lab implementation.

## Runtime Contract

- Script: `lab-tabs.js`.
- Scope: local pattern-page runtime only.
- Root selector: `[data-tabs-demo]`.
- No global keyboard listener is registered.

## Interaction

- Click activates enabled tabs.
- ArrowRight and ArrowLeft move to the next or previous enabled tab.
- Home and End move to the first or last enabled tab.
- Activation updates `aria-selected`, `tabindex`, `.is-active`, and associated panel `hidden` state.

## Ripple Decision

Phase 1 P3-1 is resolved with option (ii): Tabs uses bounded animated ripple by declaring `data-ax-ripple="bounded"` on tab hosts and consuming the existing ripple provider unchanged.

## Non-Goals

- No provider lifecycle changes.
- No router/history integration.
- No automatic activation delay or async panel loading.
