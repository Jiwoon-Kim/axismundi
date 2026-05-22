# v3.6.13 Wave 2B-4 Actions Consumers - Phase 5 Close

Status: CLOSED.

v3.6.13 closes Wave 2B-4 by adding three action-consumer lab modules:

- FAB menu #5
- Split button #7
- Toolbar #8

This also closes Wave 2B as a whole. Wave 2B now has four clean slices:
Form Controls, Dialog / Sheet, DateTime, and Actions Consumers.

## Cycle Summary

Phase 0 framed the cycle as the first multi-provider consumer-composition
cycle. Phase 1 selected Route A after checking FAB menu, Split button, and
Toolbar as distinct rows rather than a generic button-row patch. Phase 2 added
the three modules with lab-scoped CSS, module-local JS, pattern HTML, and
modern audit docs. Phase 3 validated the visual and interaction matrix.

The implementation consumed existing providers only:

- FAB menu consumes ripple and icon-system.
- Split button consumes popover, ripple, and icon-system.
- Toolbar consumes ripple and icon-system.

No provider, baseline, WordPress, styleguide, validator, generator, AGENTS.md,
or CLAUDE.md file was edited.

## Close Evidence

Phase 3 verified:

- 12 visual cells: 3 modules x desktop/mobile x light/dark.
- Console errors 0, 4xx assets 0, and horizontal overflow 0 in all 12 cells.
- `scripts/theme.js` not loaded in all three pattern pages.
- FAB menu open/close, activation, Escape close, disabled no-ripple, and
  intentional outside-click absence.
- Split button primary action / trailing chevron separation: primary click does
  not open the menu; chevron click opens the popover menu.
- Toolbar lab-scoped `aria-pressed` / `.is-selected` sync without `theme.js`.
- Ripple evidence by enabled host, with disabled hosts excluded.

Toolbar ripple clarification:

- Toolbar has 7 icon buttons total.
- 6 enabled icon buttons receive unbounded ripple.
- 1 disabled `Locked` icon button intentionally has no `data-ax-ripple`.

This preserves BACKLOG #46 separation. Future reports should count enabled
ripple hosts separately from total interactive specimen count when disabled
controls are present.

## BACKLOG Changes

No new BACKLOG item was created.

The following narrowed-open items remain unchanged:

- BACKLOG #41 shared WordPress ripple runtime packaging decision.
- BACKLOG #44 remaining specimen coverage / validator polish.
- BACKLOG #46 disabled ripple host authoring hygiene.
- BACKLOG #47 popover provider menu-item-class logic extraction hygiene.

Wave 2B close evidence is recorded inline in BACKLOG.md rather than as a new
BACKLOG row, following the v3.6.10-v3.6.12 convention for matrix row closures.

## Matrix Snapshot

Post-v3.6.13 component distribution:

```txt
34 TOC component rows:
  DONE       28
  PARTIAL     0
  TODO        3
  RECORD      3

3 infrastructure provider rows:
  popover/      DONE
  ripple/       DONE
  icon-system/  DONE

37 canonical entries total.
```

This reconciles the stale pre-close snapshot that still showed DateTime as
PARTIAL. The net movement since the user's earlier reference point is:

```txt
DONE      23 -> 28
PARTIAL    2 -> 0
TODO       6 -> 3
RECORD     3 -> 3
```

## Lock 5 Self-Application

Lock 5 has now completed three clean post-promotion self-applications:

- v3.6.11 Dialog / Sheet interaction runtime.
- v3.6.12 DateTime PARTIAL-to-DONE completion.
- v3.6.13 Actions multi-provider consumer composition.

No safe-shortcut exception was used. No fence, lock, provider, baseline, or
WordPress boundary was violated.

## Locks Preserved

- Lock 1 preserved: Axis G remained 1.000.
- Lock 2 preserved: Axis E remained 1.000.
- Lock 3 preserved: core/button semantic routing was not reopened.
- Lock 4 preserved: FAB menu, Split button, and Toolbar remained semantically
  distinct rows.
- Lock 5 preserved: Phase 0 and Phase 1 diagnostics preceded Phase 2
  implementation.

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

## Files Changed In Phase 5

- `docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-5-CLOSE.md`
- `BACKLOG.md`
- `CHANGELOG.md`
- `CURRENT-STATE.md`
- `ROADMAP.md`
- `NEXT-SESSION.md`

No implementation file was changed in Phase 5.

## Next

Wave 2B is complete. The next cycle should be plan-first under Lock 5.

Primary next candidates:

- Remaining TODO component rows, if the next module-coverage slice is desired.
- BACKLOG #21 Interpreter Plugin strategy, if the project should pivot toward
  plugin-tier architecture.
- BACKLOG #41, #44, #46, or #47 hygiene / bridge follow-ons.

The user also noted that after component modularization, a VS Code diagnostics
sweep for errors and warnings should be listed and routed. That remains a good
post-module-coverage candidate.
