# Split Button Runtime Audit

Component: Split button #7

Cycle: v3.6.13

## Runtime Split

`popover/` owns trailing menu open/close, outside click, Escape, and focus
restoration.

`lab-split-button.js` owns only validation status output:

- primary action output
- menu item activation output

It does not toggle `aria-expanded`, position a surface, or listen globally.

## Listener Boundary

The file uses one `DOMContentLoaded` setup listener with `{ once: true }`.

All click listeners are attached to controls inside `[data-split-button-demo]`.
No persistent `document` or `window` listener is added.

## Stop Conditions

If Split button needs popover provider changes, a new shared command provider,
or Button group reinterpretation, return to Phase 1 review.
