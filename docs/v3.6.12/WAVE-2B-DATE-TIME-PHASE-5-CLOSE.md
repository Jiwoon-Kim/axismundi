# v3.6.12 Wave 2B-3 DateTime #22+#23 - Phase 5 Close

Status: CLOSED
Mode: Route A, PARTIAL to DONE completion
Date: 2026-05-22

## Verdict

v3.6.12 is closed.

DateTime #22+#23 moves from PARTIAL to DONE. BACKLOG #19 Date Picker Grid
Navigation A11y is closed by v3.6.12.

This cycle is the second clean self-application after Lock 5 promotion and the
first PARTIAL-to-DONE existing-module completion cycle.

## Cycle Summary

Documents:

- `docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-0-PLAN.md`
- `docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-1-REPORT.md`
- `docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-2-REPORT.md`
- `docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-3-VISUAL-QA.md`
- `docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-5-CLOSE.md`

Route:

- Phase 0 selected Wave 2B-3 DateTime #22+#23 as the next primary route.
- Phase 1 selected Route A after proving the current `date-time/` module is
  self-contained, not an active `popover/` consumer.
- Phase 2 completed bounded Date grid a11y work inside `modules/date-time/`.
- Phase 3 verified visual, keyboard, accessibility-tree, Time picker, and
  forbidden-ancestor evidence.
- Phase 5 closes the cycle and updates state docs only.

## Close Evidence

Date grid:

- `role="grid"`: 1
- `role="row"`: 6
- `role="gridcell"`: 42
- `aria-current="date"`: 1
- `aria-selected="true"`: 1 in single mode
- `tabindex="0"`: 1
- `aria-multiselectable`: `false` in single mode, `true` in range mode
- `aria-labelledby`: `date-grid-label` -> `August 2025`

Keyboard:

- ArrowLeft / ArrowRight: PASS
- ArrowUp / ArrowDown: PASS
- Home / End: PASS
- PageUp / PageDown: PASS
- Shift+PageUp / Shift+PageDown: PASS
- Enter / Space activation: PASS
- live month/year announcement: PASS

Accessibility tree:

- Chrome DevTools Protocol `Accessibility.getFullAXTree` reported
  `grid: 1`, `row: 6`, `gridcell: 42`.
- This validates the `display: contents` row-wrapper decision for current
  Chromium accessibility output.

Time picker:

- panel role `dialog`, `aria-modal="false"`
- dial role `listbox`
- generated options `role="option"`
- 12h/24h switch PASS
- hour/minute active part switch PASS
- typed input commit PASS
- Escape close PASS

Visual:

- 4 visual cells: desktop/mobile x light/dark
- console/page errors: 0
- mobile horizontal overflow: 0
- token tuples unchanged from v3.6.11

Validation:

- `node --check lab-date-time.js`: PASS
- `wp-env run cli wp core version`: PASS, 7.0
- `python tools/generators/build_pilot_specimen_wall.py`: PASS
- `npm run validate:specimen-wall`: PASS
- `php -l products/reference-implementations/axismundi-pilot/functions.php`: PASS
- `npm test`: PASS, Axis A-G all 1.000
- `npm run validate:computed`: PASS
- `npm run publish:styleguide`: PASS, generated mirror restored
- `git diff --check`: PASS

## BACKLOG Decisions

### BACKLOG #19

Decision: close.

Reason:

- The original Date Picker Grid Navigation A11y scope is now satisfied or
  intentionally bounded.
- CDP accessibility tree evidence is accepted as the primary deterministic a11y
  evidence path for this cycle.
- Manual NVDA/VoiceOver audio testing remains useful but is not required to
  keep #19 open.

Closed scope:

- grid / row / gridcell structure
- current date
- selected date
- roving tabindex
- Arrow / Home / End / PageUp / PageDown / Shift+PageUp / Shift+PageDown
- Enter / Space activation
- polite month/year announcements

Non-goals remain out of #19:

- Time picker APG redesign
- full range-selection a11y redesign
- mobile full-screen picker variant
- locale / timezone / recurring / plugin / WordPress binding
- real `popover/` provider migration

### Provider Matrix Note

The matrix `popover/` consumer note for DateTime is treated as aspirational or
stale for v3.6.12. Do not reopen `popover/` in this close. Carry this as a
light ROADMAP / NEXT-SESSION documentation cleanup note, not a new BACKLOG item.

## Locks Preserved

- Lock 1: preserved, Axis G 1.000.
- Lock 2: preserved, Axis E 1.000.
- Lock 3: not reopened.
- Lock 4: preserved; DateTime was not reinterpreted as a popover consumer.
- Lock 5: preserved; Phase 0/1 diagnostics preceded Phase 2 implementation.

Lock 5 now has two clean post-promotion self-application cycles:

- v3.6.11 Dialog / Sheet
- v3.6.12 DateTime PARTIAL-to-DONE completion

No safe-shortcut exception was used in either cycle.

## Files Changed In Phase 5

Phase 5 changes metadata only:

- `docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-5-CLOSE.md`
- `BACKLOG.md`
- `CHANGELOG.md`
- `ROADMAP.md`
- `CURRENT-STATE.md`
- `NEXT-SESSION.md`

No implementation, baseline, provider, WordPress/Pilot, validator, generator,
styleguide source, or lock files are changed in Phase 5.

## Forward Routing

Primary next candidate:

- Wave 2B-4 Actions consumers #5 / #7 / #8.

Alternatives:

- BACKLOG #21 Interpreter Plugin strategy.
- BACKLOG #41 shared WordPress ripple runtime packaging decision.
- BACKLOG #44 remaining specimen coverage follow-ons.
- BACKLOG #46 disabled ripple host authoring hygiene.
- BACKLOG #47 popover provider menu-item-class logic extraction hygiene.
- Sheet drag-to-dismiss follow-on.

Future a11y-heavy Phase 3 cycles should consider CDP
`Accessibility.getFullAXTree` as a primary deterministic evidence path, with
manual screen-reader audio testing as supplementary evidence when needed.
