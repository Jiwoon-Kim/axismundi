# Nav Rail Spec Audit

Status: v3.6.8 Phase 2 lab implementation.

Index note: `#13` in this cycle means the Component Coverage Map TOC index from `docs/v3.5.0/COMPONENT-COVERAGE-MAP.md`, not BACKLOG.md item `#13`.

## Scope

- Owns collapsed and expanded navigation rail specimens.
- Uses lab-scoped selectors under `.lab-nav-rail-demo`.
- Demonstrates active, inactive, label, badge, and disabled destinations.
- Uses bounded animated ripple on interactive destination hosts with `data-ax-ripple="bounded"`.

## Out Of Scope

- No navigation drawer or side sheet closure.
- No adaptive shell controller.
- No changes to `components.css`, ripple, icon-system, or styleguide shell files.

## Acceptance Notes

- Collapsed and expanded rails remain distinct.
- Rail destinations preserve stable icon and label alignment.
- Disabled state remains visibly disabled and not focusable in the specimen.
