# Date/Time Picker Measurement Audit

Status: v3.6.12 Route A completion

## Baseline Reuse

The module continues to consume existing baseline primitives:

- Date picker: `stylesheets/components.css` Chunk H3, `.ax-date-picker*`
  selector family, current HEAD lines 5613-5818.
- Time picker: `stylesheets/components.css` Chunk H4, `.ax-time-picker*`
  selector family, current HEAD lines 5825-6166.

No baseline stylesheet was edited in v3.6.12.

## Lab Layer Changes

The lab layer adds only module-local behavior and documentation:

- `lab-date-time.js`: Date grid a11y completion.
- `lab-date-time.css`: `display: contents` row wrapper support for generated
  `role="row"` elements.
- `lab-date-time-pattern.html`: updated Date grid instructions and live region.
- `DATE-TIME-AUDIT.md`: small completion addendum.
- Modern audit docs: SPEC / MEASUREMENT / RUNTIME / WP mapping.

## Layout Contract

Generated `role="row"` wrappers use `.ax-date-benchmark__week { display:
contents; }` so the existing seven-column `.ax-date-picker__grid` baseline
layout remains unchanged.

The lab module does not introduce a new baseline shape, provider, or token route.

## Expected Visual QA

Phase 3 should verify:

- desktop light / dark
- mobile light / dark at 390px
- console errors: 0
- horizontal overflow: 0
- Date grid row wrappers do not alter the visible grid layout
- Time picker layout remains unchanged
