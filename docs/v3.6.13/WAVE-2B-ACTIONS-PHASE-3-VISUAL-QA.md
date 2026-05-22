# v3.6.13 Wave 2B-4 Actions Consumers - Phase 3 Visual / Interaction QA

Status: GO for Phase 5 close.

This phase validates the Route A implementation for FAB menu, Split button, and
Toolbar. The test target was the repo-root localhost server:

`http://127.0.0.1:8794/products/reference-implementations/axismundi-lab/modules/...`

The repo-root server convention avoided the self-hosted font/icon 404s that
appear when serving from the lab subdirectory.

## Lock 5 Self-Application

Phase 3 remained read-only. No implementation, baseline, provider, WordPress,
styleguide, validator, generator, AGENTS.md, or CLAUDE.md files were changed.
No safe-shortcut exception was used.

## Visual Matrix

3 modules x desktop/mobile x light/dark = 12 cells.

| Module | Viewport | Theme | Console errors | 4xx assets | Overflow X | Body bg | Body color | theme.js loaded |
|---|---|---|---:|---:|---:|---|---|---|
| FAB menu | desktop 1280x720 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| FAB menu | desktop 1280x720 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |
| FAB menu | mobile 390x844 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| FAB menu | mobile 390x844 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |
| Split button | desktop 1280x720 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| Split button | desktop 1280x720 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |
| Split button | mobile 390x844 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| Split button | mobile 390x844 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |
| Toolbar | desktop 1280x720 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| Toolbar | desktop 1280x720 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |
| Toolbar | mobile 390x844 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| Toolbar | mobile 390x844 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |

Token values match the v3.6.12 close values. Lock 1 and Lock 2 remain
preserved.

## FAB Menu Interaction

| Check | Result |
|---|---|
| Trigger click | `aria-expanded="true"`, menu `.is-open=true`, list `aria-hidden="false"` |
| Initial focus | first enabled action, text `edit New post` |
| Action click | status `Activated: edit New post`, menu closes |
| Outside click | menu stays open, `aria-expanded="true"`; outside-click is intentionally absent |
| Escape while focus is inside menu | menu closes, `aria-expanded="false"`, focus restores to trigger |
| Disabled action | 1 disabled action, 0 disabled ripple hosts |

FAB menu consumes ripple only. It does not consume popover and does not create a
document/window outside-click listener. The intentional outside-click absence is
therefore verified rather than treated as a regression.

## Split Button Interaction

| Check | Result |
|---|---|
| Primary click | status `Primary action: Save`, `aria-expanded="false"`, open menus 0 |
| Chevron click | `aria-expanded="true"`, open menus 1, focused item `Save draft` |
| Menu item click | status `Menu action: Save draft`, menu closes, `aria-expanded="false"` |
| Tab order | primary -> chevron; active chevron has `aria-controls="split-save-menu"` |
| Primary trigger attrs | 2 primary segments, all `aria-haspopup=false`, `aria-controls=false`, `data-popover-trigger=false` |
| Chevron trigger attrs | 2 chevrons with `data-popover-trigger`, `aria-haspopup="menu"`, and `aria-controls` |

This confirms the Phase 2 P3 split: primary action and trailing menu affordance
remain distinct. Popover is consumed only by the trailing chevron surfaces.

## Toolbar Interaction

| Check | Result |
|---|---|
| Loaded scripts | `../ripple/lab-ripple.js`, `lab-toolbar.js` |
| `theme.js` loaded | false |
| Toolbar count | 2 `role="toolbar"` containers |
| Italic click | `aria-pressed="true"`, `.is-selected=true`, status `Italic: on` |
| Disabled Locked click | `aria-pressed="false"`, `.is-selected=false`, no `data-ax-ripple` |
| Programmatic dark theme | body bg `rgb(20, 18, 24)`, body color `rgb(230, 224, 233)` |

The pattern page intentionally omits `scripts/theme.js`; the local toolbar
runtime owns only the lab-scoped `aria-pressed` and `.is-selected` sync.

## Ripple Evidence

| Module | Evidence |
|---|---|
| FAB menu | 2 unbounded hosts, 4 bounded hosts, 0 disabled ripple hosts, enabled pointer creates `.ax-ripple` |
| Split button | 8 bounded hosts, 0 unbounded hosts, 0 disabled ripple hosts, enabled pointer creates `.ax-ripple` |
| Toolbar | 6 enabled unbounded icon hosts, 1 bounded text host, 1 disabled icon with no ripple, enabled pointer creates `.ax-ripple` |

Toolbar has 7 icon buttons total: 6 enabled icon buttons receive unbounded
ripple, while the disabled `Locked` icon button intentionally has no
`data-ax-ripple`. This preserves BACKLOG #46 separation. Treat the Phase 2
shorthand "Toolbar icon buttons x7" as "7 icon buttons total, 6 enabled ripple
hosts plus 1 disabled no-ripple host."

## Phase 2 P3 Absorption

| Phase 2 P3 | Phase 3 result |
|---|---|
| P3-1 Split primary/chevron focus isolation | PASS: Tab order primary -> chevron; primary has no popover attrs; chevron opens popover |
| P3-2 FAB outside-click absence | PASS: outside click leaves menu open; Escape closes when focus is inside menu |
| P3-3 Toolbar theme.js isolation | PASS: theme.js not loaded; local toolbar runtime toggles state without collision |
| P3-4 Matrix snapshot cleanup | Carry to Phase 5 |

## Fences

Preserved:

- AGENTS.md / CLAUDE.md
- theme.json / functions.php
- components.css / blocks.css / style-guide.html
- scripts/style-guide.js / scripts/theme.js
- popover/ ripple/ icon-system/
- 11 closed module directories and 5 form-adjacent modules outside v3.6.13
- validators and generator files

## Validation

All commands PASS:

```txt
node --check products/reference-implementations/axismundi-lab/modules/fab-menu/lab-fab-menu.js
node --check products/reference-implementations/axismundi-lab/modules/split-button/lab-split-button.js
node --check products/reference-implementations/axismundi-lab/modules/toolbar/lab-toolbar.js
wp-env run cli wp core version
python tools/generators/build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products/reference-implementations/axismundi-pilot/functions.php
npm test
npm run validate:computed
npm run publish:styleguide
git diff --check
```

`wp-env` reported WordPress 7.0. `npm test` reported overall 1.000 with Axis
A-G all 1.000. `publish:styleguide` generated mirror artifacts only; those were
restored after validation.

## Phase 5 Carry-Forward

1. Update CURRENT-STATE.md Matrix Snapshot to reflect post-v3.6.13 expected
   counts: DONE 28, PARTIAL 0, TODO 3, RECORD 3.
2. Close Wave 2B-4 Actions consumers if reviewer accepts the Toolbar ripple
   clarification above.
3. Route next cycle candidates: BACKLOG #21, #41, #44, #46, #47, or remaining
   TODO components.
