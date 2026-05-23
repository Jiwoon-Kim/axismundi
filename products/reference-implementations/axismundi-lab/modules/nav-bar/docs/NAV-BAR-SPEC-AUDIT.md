# Nav Bar Spec Audit

Status: v3.6.8 Phase 2 lab implementation.

Index note: `#12` in this cycle means the Component Coverage Map TOC index from `docs/v3.5.0/COMPONENT-COVERAGE-MAP.md`, not BACKLOG.md item `#12`.

## Scope

- Owns mobile bottom-navigation specimens.
- Uses lab-scoped selectors under `.lab-nav-bar-demo`.
- Demonstrates active, inactive, badge, and disabled destinations.
- Uses bounded animated ripple on interactive destination hosts with `data-ax-ripple="bounded"`.

## Out Of Scope

- No route state manager.
- No WordPress Navigation block binding.
- No changes to the ripple provider.

## Acceptance Notes

- Destinations expose stable labels and icons.
- Disabled destination is visibly disabled and not focusable in the specimen.
- Ripple usage is consumer-only; provider code remains untouched.

## v3.6.16 Diagnostic Addendum

- Active navigation destinations use `aria-current="page"`.
- The nav bar lab pattern does not use the `tablist` / `tab` model, and it does
  not use `aria-selected` for destination state.
