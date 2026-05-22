# Split Button Spec Audit

Component: Split button #7

Cycle: v3.6.13 Wave 2B-4 Actions Consumers

## Verdict

Split button closes as a primary action plus trailing menu trigger.

## Markup Contract

- Parent: `<div class="ax-split-button" role="group" aria-label="...">`
- Primary segment: native `<button type="button">`, no `aria-haspopup`.
- Trailing segment: native `<button type="button">` with
  `data-popover-trigger`, `aria-haspopup="menu"`, `aria-expanded`, and
  `aria-controls`.

## Semantics

The primary segment performs the default action. The trailing segment opens the
menu. These semantics must not collapse into Button group.

## Dependencies

- `components.css` Chunk H2 for split-button visuals.
- Button #1 baseline classes for segment styling.
- `popover/` for trailing menu positioning/dismissal.
- `ripple/` bounded hosts.
