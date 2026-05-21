# App Bar Spec Audit

Status: v3.6.8 Phase 2 lab implementation.

Index note: `#11` in this cycle means the Component Coverage Map TOC index from `docs/v3.5.0/COMPONENT-COVERAGE-MAP.md`, not BACKLOG.md item `#11`.

## Scope

- Owns static app bar specimens for small, medium, large, and scrolled-density examples.
- Uses lab-scoped selectors under `.lab-app-bar-demo`.
- Reuses existing Material Symbols markup policy: `material-symbols-rounded notranslate`, `translate="no"`, `aria-hidden="true"`.
- Keeps action-slot ripple as CANDIDATE. No animated ripple is attached in v3.6.8.

## Out Of Scope

- No navigation drawer, side sheet, or route shell closure.
- No Menu or popover coupling.
- No changes to `components.css`, styleguide shell chrome, or provider modules.

## Acceptance Notes

- Pattern page renders without JavaScript.
- Header, leading icon, title/subtitle, actions, and prominent variants remain inspectable at desktop and mobile widths.
- Visual primitives come from the existing component baseline; module CSS only frames the lab specimen.
