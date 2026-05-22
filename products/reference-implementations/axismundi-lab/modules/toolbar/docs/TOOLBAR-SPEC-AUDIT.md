# Toolbar Spec Audit

Component: Toolbar #8

Cycle: v3.6.13 Wave 2B-4 Actions Consumers

## Verdict

Toolbar closes as a public action container with local toggle-state specimens.

## Contract

- Host uses `.ax-toolbar`.
- Toolbar has `role="toolbar"` and an accessible label.
- Toggle controls are native buttons with `aria-pressed`.
- Non-interactive spacer uses `.ax-toolbar__spacer` and no ripple host.

## Boundary

Toolbar is not Button group. It may contain Button or Icon button controls, but
the toolbar owns command grouping and pressed-state presentation.

Toolbar is not editor/plugin UI. Actual command execution is out of scope.

## Dependencies

- `components.css §29` for baseline visuals.
- `ripple/` for declarative ripple.
- `icon-system/` for Material Symbols.
