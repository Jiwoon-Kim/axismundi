# FAB Menu Spec Audit

Component: FAB menu #5

Cycle: v3.6.13 Wave 2B-4 Actions Consumers

## Verdict

FAB menu closes as a self-contained expanded action set. It does not consume
`popover/` in v3.6.13.

## Contract

- Host uses `.ax-fab-menu`.
- Open state is `.is-open`.
- Toggle control is a native `<button>`.
- Action rows are native `<button>` elements inside list items.
- Disabled action rows use native `disabled` and do not receive animated ripple.

## Role Decision

Use native button-list semantics.

Do not use `role="menu"` / `role="menuitem"` because FAB menu actions are not
the WAI-ARIA Menu Button pattern. Menu #15 remains the menu semantic owner.

## Dependencies

- `components.css` Chunk H1 for baseline visuals.
- `ripple/` declarative provider.
- `icon-system/` Material Symbols policy.

No provider file is changed by this module.
