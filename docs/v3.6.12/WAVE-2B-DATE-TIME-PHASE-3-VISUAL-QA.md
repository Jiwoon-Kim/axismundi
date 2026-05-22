# v3.6.12 Wave 2B-3 DateTime #22+#23 - Phase 3 Visual / Keyboard QA

Status: Phase 3 QA submitted for review
Mode: read-only QA after Route A implementation
Test target: repo-root localhost server, `http://127.0.0.1:8795/.../lab-date-time-pattern.html`

## Verdict

GO for Phase 5 close.

The Date grid completion is verified:

- visual matrix clean
- Date grid keyboard matrix clean
- accessibility tree exposes `grid: 1`, `row: 6`, `gridcell: 42`
- live month/year announcements update on month/year navigation
- single/range `aria-multiselectable` behavior correct
- Time picker non-regression clean
- provider, baseline, WordPress/Pilot, and lock fences unchanged

## Visual Matrix

| Theme | Viewport | Console/Page Errors | Overflow X | Body BG | Body Color |
|---|---|---:|---:|---|---|
| light | desktop | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` |
| light | mobile 390px | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` |
| dark | desktop | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` |
| dark | mobile 390px | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` |

Token tuples match v3.6.11 close evidence. Lock 1 / Lock 2 remain preserved.

## Date Grid Structure

After opening the Date picker:

| Check | Result |
|---|---|
| grid role | `grid` |
| grid labelledby | `date-grid-label` |
| label text | `August 2025` |
| row count in DOM | 6 |
| gridcell count in DOM | 42 |
| `aria-current="date"` count | 1 |
| selected cell count | 1 |
| `tabindex="0"` count | 1 |
| focused date after open | `2025-08-17` |
| single mode `aria-multiselectable` | `false` |
| range mode `aria-multiselectable` | `true` |

Accessibility tree via Chrome DevTools Protocol `Accessibility.getFullAXTree`:

| Role | Count |
|---|---:|
| grid | 1 |
| row | 6 |
| gridcell | 42 |

This is the Phase 2 P3-2 row-wrapper validation. The `display: contents` row
wrapper preserves visual layout while the accessibility tree still exposes row
semantics.

## Date Keyboard Matrix

Initial focus after open: `2025-08-17`.

| Key | Focused Date | Live Text | Roving `tabindex=0` |
|---|---|---|---|
| ArrowRight | `2025-08-18` | `August 2025` | `2025-08-18` |
| ArrowLeft | `2025-08-17` | `August 2025` | `2025-08-17` |
| ArrowDown | `2025-08-24` | `August 2025` | `2025-08-24` |
| ArrowUp | `2025-08-17` | `August 2025` | `2025-08-17` |
| End | `2025-08-23` | `August 2025` | `2025-08-23` |
| Home | `2025-08-17` | `August 2025` | `2025-08-17` |
| PageDown | `2025-09-17` | `September 2025` | `2025-09-17` |
| PageUp | `2025-08-17` | `August 2025` | `2025-08-17` |
| Shift+PageDown | `2026-08-17` | `August 2026` | `2026-08-17` |
| Shift+PageUp | `2025-08-17` | `August 2025` | `2025-08-17` |

Activation:

| Action | Result |
|---|---|
| Enter | selected `2025-08-17`, headline `Sun, Aug 17` |
| Space | selected `2025-08-17`, headline `Sun, Aug 17` |

## Time Picker Non-Regression

| Check | Result |
|---|---|
| open dial | surface visible |
| panel role | `dialog` |
| `aria-modal` | `false` |
| dial role | `listbox` |
| initial option count | 12 |
| selected option count | 1 |
| initial docked value | `07:00 AM` |
| 24h switch | `aria-pressed="true"`, option count 24 |
| minute active part | `aria-pressed="true"`, option count 12 |
| typed input commit | `11:45`, surface hidden |
| Escape close | surface hidden |

The Time picker keeps the v3.6.12 listbox/option contract. No radiogroup,
spinbutton, native time input, timezone, locale, or provider rewrite was
introduced.

## Forbidden-Ancestor Probe

Pattern page contains one `.prose [data-date-benchmark]` negative specimen.

Observed:

- forbidden root count: 1
- docked input value remains `N/A`
- no active initialized date surface is created inside the forbidden specimen

This confirms the existing forbidden-ancestor initialization bail-out is
preserved.

## Validation

| Command | Result |
|---|---|
| `node --check products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js` | PASS |
| `wp-env run cli wp core version` | PASS - 7.0 |
| `python tools/generators/build_pilot_specimen_wall.py` | PASS |
| `npm run validate:specimen-wall` | PASS |
| `php -l products/reference-implementations/axismundi-pilot/functions.php` | PASS |
| `npm test` | PASS - overall 1.000, Axis A-G all 1.000 |
| `npm run validate:computed` | PASS |
| `npm run publish:styleguide` | PASS |
| `git diff --check` | PASS |

Validator-generated tracked reports and publish mirror files were restored after
validation. No artifact churn remains in the intended Phase 3 diff.

## Fence Check

Unchanged during Phase 3:

- `AGENTS.md`
- `CLAUDE.md`
- WordPress / Pilot files
- `stylesheets/components.css`
- `stylesheets/blocks.css`
- `style-guide.html`
- `scripts/style-guide.js`
- `popover/`
- `ripple/`
- `icon-system/`
- closed Wave 2A / Wave 2B modules
- validators and generator
- v3.6.12 Phase 2 implementation files

Phase 3 is report-only.

## BACKLOG #19 Close Readiness

Phase 3 evidence supports closing BACKLOG #19 in Phase 5:

- row semantics are exposed in the accessibility tree
- gridcell count is complete
- roving tabindex is preserved
- current date is exposed
- selected date is exposed
- single/range multiselectable semantics are explicit
- keyboard movement covers Arrow, Home/End, PageUp/PageDown, Shift+PageUp/Down
- live month/year text updates

Remaining non-goals are not BACKLOG #19:

- Time picker APG redesign
- full range-selection a11y redesign
- mobile full-screen picker variant
- locale/timezone/recurring/plugin/WordPress binding
- real `popover/` provider migration

## Phase 5 Recommendations

- Close Date+Time #22+#23 as DONE if reviewer accepts this evidence.
- Close BACKLOG #19, or narrow only if reviewer requires manual NVDA/VoiceOver
  audio evidence beyond the CDP accessibility tree.
- Keep provider-matrix `popover/` relationship as routed/stale note for future
  documentation cleanup rather than reopening `popover/` in this cycle.
- Preserve the legacy `DATE-TIME-AUDIT.md` addendum + modern docs pattern.
