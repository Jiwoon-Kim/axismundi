# Tabs Spec Audit

Status: v3.6.8 Phase 2 lab implementation.

Index note: `#14` in this cycle means the Component Coverage Map TOC index from `docs/v3.5.0/COMPONENT-COVERAGE-MAP.md`, not BACKLOG.md item `#14`.

## Scope

- Owns primary, secondary, icon, and disabled tab specimens.
- Uses lab-scoped selectors under `.lab-tabs-demo`.
- Uses bounded animated ripple on tab hosts with `data-ax-ripple="bounded"`.
- Includes component-local JavaScript for tab selection and keyboard movement.

## Out Of Scope

- No router integration.
- No global keyboard listeners.
- No provider module changes.
- No WordPress block binding.

## Acceptance Notes

- Tabs expose `role="tablist"`, `role="tab"`, and `role="tabpanel"`.
- Selected tabs use `aria-selected="true"` and `tabindex="0"`.
- Disabled tabs remain unavailable to click and keyboard activation.
