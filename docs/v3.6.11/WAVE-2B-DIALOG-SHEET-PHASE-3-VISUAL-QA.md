# v3.6.11 Wave 2B-2 Dialog / Sheet - Phase 3 Visual QA

Status: GO for Phase 5 close

Date: 2026-05-22

Mode: read-only visual / interaction confirmation after Route A implementation.

## Verdict

Route A remains valid.

Dialog and Sheet passed the v3.6.11 portal / overlay smoke matrix:

- 8 visual cells: PASS
- Dialog interaction matrix: PASS
- Sheet interaction matrix: PASS
- Phase 2 P3 checks: absorbed
- Lock 1-5: preserved
- Implementation / provider / baseline fences: preserved

Phase 5 may close v3.6.11 if the metadata documents route the remaining
drag-to-dismiss follow-on without reopening implementation files.

## Scope

Phase 3 added this report only.

No implementation file was edited in Phase 3.

The test target was a repo-root local server:

```text
http://127.0.0.1:8792/products/reference-implementations/axismundi-lab/modules/dialog/lab-dialog-pattern.html
http://127.0.0.1:8792/products/reference-implementations/axismundi-lab/modules/sheet/lab-sheet-pattern.html
```

This follows the v3.6.9 test-target convention: use a repo-root localhost
server for module QA unless a cycle has a more specific target.

## Visual Matrix

All visual cells used Playwright Chromium with programmatic theme setting:

```js
document.documentElement.setAttribute("data-theme", theme)
```

The pattern pages do not load `scripts/theme.js`; the test sets the attribute
directly, as in v3.6.10.

| Module | Viewport | Theme | Console errors | Horizontal overflow | Body bg | Body color | Focus outline |
|---|---:|---|---:|---:|---|---|---|
| Dialog | 1280x800 | light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | `2px solid rgb(98, 91, 113)` |
| Dialog | 1280x800 | dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | `2px solid rgb(204, 194, 220)` |
| Dialog | 390x900 | light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | `2px solid rgb(98, 91, 113)` |
| Dialog | 390x900 | dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | `2px solid rgb(204, 194, 220)` |
| Sheet | 1280x800 | light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | `2px solid rgb(98, 91, 113)` |
| Sheet | 1280x800 | dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | `2px solid rgb(204, 194, 220)` |
| Sheet | 390x900 | light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | `2px solid rgb(98, 91, 113)` |
| Sheet | 390x900 | dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | `2px solid rgb(204, 194, 220)` |

Surface counts:

| Module | Triggers | Surfaces | Scrims |
|---|---:|---:|---:|
| Dialog | 2 | 2 native `dialog.dialog` hosts | 1 |
| Sheet | 2 | 2 `.sheet[role="dialog"]` hosts | 1 |

Token evidence matches v3.6.5-v3.6.10 close values for surface,
on-surface, and outline colors.

## Dialog Interaction Matrix

### Basic Dialog

Trigger:

```text
button[aria-controls="dialog-basic"]
```

Open result:

| Check | Observed | Result |
|---|---|---|
| `#dialog-basic.open` | `true` | PASS |
| Scrim `data-open` | `"true"` | PASS |
| Scrim `.is-open` | `true` | PASS |
| Initial focus | `취소` | PASS |
| `aria-labelledby` | `dialog-basic-title` | PASS |
| `aria-describedby` | `dialog-basic-supporting` | PASS |

Keyboard / close paths:

| Check | Observed | Result |
|---|---|---|
| Tab after initial focus | focus moved to `저장`, still inside dialog | PASS |
| Escape / native cancel | `cancelEvents: 1`, `closeEvents: 1`, dialog closed | PASS |
| Focus restoration after Escape | active trigger `aria-controls="dialog-basic"` | PASS |

### Basic Backdrop / Scrim Paths

Phase 2 P3 requested explicit evidence for native `::backdrop` versus
`.modal-scrim` behavior.

Real pointer backdrop click:

```text
click: (20, 20)
target: DIALOG#dialog-basic.dialog.dialog--basic
scrimClicks: 0
dialogBackdropClicks: 1
closeEvents: +1
result: closed, scrim data-open false, focus restored
```

This shows that while the native `<dialog>` is in the top layer, a real user
pointer outside the visible dialog reaches the native dialog backdrop path.
There was no double-fire.

Defensive `.modal-scrim` path:

```text
programmatic scrim click target: DIV.modal-scrim.is-open
scrimClicks: 1
dialogBackdropClicks: unchanged
closeEvents: +1
result: closed, scrim data-open false, focus restored
```

This confirms that the `.modal-scrim` handler is functional and idempotent,
but the real pointer path for the native basic dialog is the native dialog
backdrop handler in Chromium.

### Full-Screen Dialog

Trigger:

```text
button[aria-controls="dialog-fullscreen"]
```

| Check | Observed | Result |
|---|---|---|
| `#dialog-fullscreen.open` | `true` | PASS |
| Initial focus | `close` icon button | PASS |
| `aria-labelledby` | `dialog-fullscreen-title` | PASS |
| `aria-describedby` | `null` | PASS - no supporting text in this specimen |
| Close button | closes dialog | PASS |
| Focus restoration after close button | active trigger `aria-controls="dialog-fullscreen"` | PASS |

## Sheet Interaction Matrix

### Bottom Sheet

Trigger:

```text
button[aria-controls="sheet-bottom"]
```

Open result:

| Check | Observed | Result |
|---|---|---|
| `.is-open` | `true` | PASS |
| `aria-hidden` | `false` | PASS |
| Scrim `data-open` | `"true"` | PASS |
| Scrim `.is-open` | `true` | PASS |
| Initial focus | `링크 복사` | PASS |
| `role` | `dialog` | PASS |
| `aria-modal` | `true` | PASS |
| `aria-labelledby` | `sheet-bottom-title` | PASS |

Focus containment:

| Check | Observed | Result |
|---|---|---|
| Shift+Tab from first focusable | wraps to `닫기`, still inside sheet | PASS |
| Tab after wrap | returns to `링크 복사`, still inside sheet | PASS |
| Escape | closes sheet, scrim false, focus restored to trigger | PASS |
| Scrim click | closes sheet, scrim false, focus restored to trigger | PASS |

### Side Sheet

Trigger:

```text
button[aria-controls="sheet-side"]
```

Open result:

| Check | Observed | Result |
|---|---|---|
| `.is-open` | `true` | PASS |
| `aria-hidden` | `false` | PASS |
| Scrim `data-open` | `"true"` | PASS |
| Scrim `.is-open` | `true` | PASS |
| Initial focus | `적용` | PASS |
| `role` | `dialog` | PASS |
| `aria-modal` | `true` | PASS |
| `aria-labelledby` | `sheet-side-title` | PASS |

Focus / outside click:

| Check | Observed | Result |
|---|---|---|
| Tab from initial focus | moves to `닫기`, still inside sheet | PASS |
| Outside click outside measured sheet rect | closes sheet | PASS |
| Focus restoration after outside click | active trigger `aria-controls="sheet-side"` | PASS |

The first exploratory click at `x=100` was inside the measured side-sheet rect,
so it was not counted as an outside-click probe. The final outside-click probe
used a coordinate outside the measured sheet rect and closed through the scrim.

### Drag-To-Dismiss

| Check | Observed | Result |
|---|---|---|
| Defer note visible | `true` | PASS |
| Interactive drag-to-dismiss runtime | none | PASS - intentionally deferred |

## Phase 2 P3 Absorption

| Phase 2 P3 | Phase 3 result |
|---|---|
| P3-1 Backdrop double-fire behavior | Real pointer path: native dialog handler only. Defensive scrim path: functional. No double-fire observed. |
| P3-2 Sheet keydown attach point under focus loss | Tab and Shift+Tab remain inside the sheet. Outside click reaches scrim and closes with focus restoration. |
| P3-3 Drag-to-dismiss routing | Carry to Phase 5. Recommendation: ROADMAP / NEXT-SESSION follow-on, no new BACKLOG item unless review asks. |
| P3-4 Native `::backdrop` transparency dependency | Carry to Phase 5 as a future baseline-update note. |

## Lock / Fence Confirmation

No Phase 3 implementation edits were made.

Fences preserved:

- `AGENTS.md` / `CLAUDE.md`
- `theme.json` / `functions.php`
- `components.css` / `blocks.css`
- `style-guide.html`
- `scripts/style-guide.js`
- `scripts/theme.js`
- `modules/popover/*`
- `modules/ripple/*`
- `modules/icon-system/*`
- closed Wave 2A modules
- closed Wave 2B-1 form modules
- existing form-adjacent modules
- validators / generator

Lock status:

| Lock | Status |
|---|---|
| Lock 1 - wp-custom downstream-only | Preserved; Axis G `1.000` |
| Lock 2 - md-sys maps to md-ref | Preserved; Axis E `1.000` |
| Lock 3 - core/button semantic route | Not reopened |
| Lock 4 - semantic mismatch handling | Dialog / Sheet remained independent, not popover consumers |
| Lock 5 - diagnostic-first | Preserved; Phase 0 / 1 preceded Phase 2 implementation |

## Validation

| Command | Result |
|---|---|
| `node --check products/reference-implementations/axismundi-lab/modules/dialog/lab-dialog.js` | PASS |
| `node --check products/reference-implementations/axismundi-lab/modules/sheet/lab-sheet.js` | PASS |
| `npx wp-env run cli wp core version` | PASS - `7.0` |
| `python tools/generators/build_pilot_specimen_wall.py` | PASS - pages 29 and 41 updated |
| `npm run validate:specimen-wall` | PASS |
| `php -l products/reference-implementations/axismundi-pilot/functions.php` | PASS |
| `npm test` | PASS - overall `1.000`, Axis A-G all `1.000` |
| `npm run validate:computed` | PASS |
| `npm run publish:styleguide` | PASS - generated mirror restored |
| `git diff --check` | PASS |

Validator-generated reports and publish mirror files were restored after
validation. No validator or styleguide artifact churn remains in the intended
Phase 3 diff.

## P3 Notes For Phase 5

### P3-1 - Drag-To-Dismiss Routing

Phase 5 should decide how to carry the Sheet drag-to-dismiss defer.

Recommended decision: route through ROADMAP / CURRENT-STATE / NEXT-SESSION as
a Wave 2B-2 follow-on note, not a new BACKLOG item. Modal Sheet closure is
complete without drag-to-dismiss, and the v3.6.10 convention avoids BACKLOG
fragmentation unless there is specific routed work.

### P3-2 - Native Backdrop / External Scrim Layering

Chromium real-pointer behavior for the native basic dialog is:

```text
real outside click -> DIALOG#dialog-basic backdrop path
programmatic scrim click -> DIV.modal-scrim defensive path
```

The current implementation is safe and idempotent. If a future baseline cycle
changes `.dialog::backdrop` from transparent to visually styled, revisit this
layering so native backdrop and `.modal-scrim` do not produce conflicting
visual scrims.

## Next

Submit for Phase 3 review.

If approved, Phase 5 should update:

- `docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-5-CLOSE.md`
- `BACKLOG.md`
- `CHANGELOG.md`
- `ROADMAP.md`
- `CURRENT-STATE.md`
- `NEXT-SESSION.md`

Implementation files should remain unchanged in Phase 5.
