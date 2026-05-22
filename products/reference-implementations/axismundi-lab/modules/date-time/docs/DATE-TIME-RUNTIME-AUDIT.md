# Date/Time Picker Runtime Audit

Status: v3.6.12 Route A completion

## Runtime Ownership

`lab-date-time.js` remains a self-contained lab module runtime. It does not
consume `window.labPopover`, `data-popover-trigger`, `ripple/`, or
`icon-system/`.

## Date Grid Completion

v3.6.12 completes the bounded Date grid contract:

- generated row wrappers with `role="row"`
- generated date buttons with `role="gridcell"`
- `aria-current="date"` on the demo today cell
- `aria-labelledby` from grid to month/year label
- `aria-multiselectable` toggled by single/range mode
- polite live month/year announcement region
- ArrowLeft / ArrowRight / ArrowUp / ArrowDown
- Home / End
- PageUp / PageDown
- Shift+PageUp / Shift+PageDown
- Enter / Space through native button activation
- Escape close

The implementation keeps the existing local root-level keydown listener. No
document or window persistent keyboard listener is added.

## Time Picker Runtime

The Time picker keeps its existing local runtime:

- listbox dial host
- generated option buttons
- hour/minute active part
- 12h/24h format
- AM/PM period switching
- direct input parsing
- OK / Cancel commit
- Escape close

No Time picker APG rewrite is performed in v3.6.12.

## Provider Boundary

The matrix `popover/` consumer note is treated as aspirational/stale for this
cycle. Migrating Date+Time to `popover/` would be a future Route D requiring a
fresh Phase 0/1 review.

## Future Review Triggers

Return for review if future work needs:

- `popover/` provider migration
- a shared calendar or overlay provider
- full range-selection a11y semantics
- mobile full-screen picker behavior
- locale/timezone/recurring event logic
- WordPress or plugin bindings
