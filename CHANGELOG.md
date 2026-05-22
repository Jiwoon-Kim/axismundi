# CHANGELOG

## v3.6.13 - Wave 2B-4 Actions Consumers (2026-05-22)

Multi-provider consumer-composition release that closes FAB menu #5, Split
button #7, and Toolbar #8. This completes Wave 2B.

### Added

- Added the v3.6.13 Phase 0/1/2/3/5 docs under `docs/v3.6.13/`.
- Added `modules/fab-menu/` with lab-scoped CSS, local runtime, pattern HTML,
  and SPEC / MEASUREMENT / RUNTIME / WP audit docs.
- Added `modules/split-button/` with lab-scoped CSS, local status runtime,
  pattern HTML, and SPEC / MEASUREMENT / RUNTIME / WP audit docs.
- Added `modules/toolbar/` with lab-scoped CSS, local `aria-pressed` runtime,
  pattern HTML, and SPEC / MEASUREMENT / RUNTIME / WP audit docs.

### Routed

- Completed Wave 2B: Form Controls, Dialog / Sheet, DateTime, and Actions
  Consumers are now closed.
- Kept `popover/`, `ripple/`, and `icon-system/` unchanged; the new modules are
  declarative consumers only.
- Kept `scripts/theme.js` unchanged and unloaded by the Toolbar pattern page to
  avoid the styleguide global toolbar precedent.
- Kept BACKLOG #41, #44, #46, and #47 unchanged.
- Carried the VS Code diagnostics sweep as a good post-module-coverage
  candidate after component modularization.

### Verified

- 12 visual cells: 3 modules x desktop/mobile x light/dark, console 0, 4xx 0,
  and overflow 0.
- `theme.js` no-load verified in FAB menu, Split button, and Toolbar pattern
  pages.
- FAB menu: open, activation, Escape close, disabled no-ripple, and intentional
  outside-click absence PASS.
- Split button: primary action and trailing chevron popover trigger remained
  distinct; primary has no `aria-haspopup`, `aria-controls`, or
  `data-popover-trigger`.
- Toolbar: lab-scoped `aria-pressed` / `.is-selected` toggle PASS without
  `theme.js`.
- Ripple count clarified for BACKLOG #46 separation: Toolbar has 7 icon buttons
  total, 6 enabled unbounded ripple hosts, and 1 disabled no-ripple host.
- `node --check` for all three new JS files: PASS.
- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS; Axis A/B/C/D/E/F/G all 1.000.
- `npm run validate:computed`: PASS.
- `npm run publish:styleguide`: PASS, with generated mirror restored after
  validation.
- `git diff --check`: PASS.

## v3.6.12 - Wave 2B-3 DateTime (2026-05-22)

Existing-module completion release that moves DateTime #22+#23 from PARTIAL to
DONE and closes BACKLOG #19 Date Picker Grid Navigation A11y.

### Added

- Added the v3.6.12 Phase 0/1/2/3/5 docs under `docs/v3.6.12/`.
- Completed the bounded Date grid a11y contract inside existing
  `modules/date-time/`: row/gridcell structure, `aria-current`, grid labeling,
  `aria-multiselectable`, Home/End, PageUp/PageDown, Shift+PageUp/Down,
  Enter/Space activation, and polite month/year announcements.
- Added modern DateTime SPEC / MEASUREMENT / RUNTIME / WP audit docs while
  preserving `DATE-TIME-AUDIT.md` as the v3.4.7 provenance audit with a v3.6.12
  addendum.

### Routed

- Closed BACKLOG #19 by accepting CDP `Accessibility.getFullAXTree` evidence as
  the primary deterministic a11y evidence path for this cycle.
- Kept Time picker APG redesign, full range-selection a11y, mobile full-screen
  DateTime, locale/timezone/recurring behavior, plugin/WordPress binding, and
  real `popover/` provider migration out of scope.
- Kept the stale/aspirational DateTime `popover/` provider-matrix note as a
  light future documentation cleanup, not a new BACKLOG item.
- Kept BACKLOG #41, #44, #46, and #47 unchanged.

### Verified

- 4 visual cells: desktop/mobile x light/dark, console 0 and overflow 0.
- Date grid structure: `grid: 1`, `row: 6`, `gridcell: 42`,
  `aria-current="date": 1`, `tabindex="0": 1`.
- Date keyboard: Arrow, Home/End, PageUp/PageDown, Shift+PageUp/Down,
  Enter/Space, roving tabindex, and live month/year announcements PASS.
- Time picker non-regression: `dialog` + `listbox` / `option`, 12h/24h switch,
  hour/minute part switch, typed input commit, and Escape close PASS.
- Forbidden-ancestor specimen remained inert.
- `node --check products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js`:
  PASS.
- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS; Axis A/B/C/D/E/F/G all 1.000.
- `npm run validate:computed`: PASS.
- `npm run publish:styleguide`: PASS, with generated mirror restored after
  validation.
- `git diff --check`: PASS.

## v3.6.11 - Wave 2B-2 Dialog / Sheet (2026-05-22)

Interaction-runtime component-lab release that closes the second Wave 2B
slice: Dialog #26 and Sheet #27. This cycle implements Route A, Dialog + Sheet
module-local runtime, and is the first full self-application cycle after Lock 5
promotion.

### Added

- Added the v3.6.11 Phase 0/1/2/3/5 docs under `docs/v3.6.11/`.
- Added `modules/dialog/` with lab-scoped CSS, native `<dialog>` pattern HTML,
  `lab-dialog.js`, and SPEC/MEASUREMENT/RUNTIME/WP audit docs.
- Added `modules/sheet/` with lab-scoped CSS, modal Sheet pattern HTML,
  `lab-sheet.js`, and SPEC/MEASUREMENT/RUNTIME/WP audit docs.

### Routed

- Kept `components.css`, `blocks.css`, `style-guide.html`,
  `scripts/style-guide.js`, and provider modules unchanged.
- Kept Dialog / Sheet independent and did not reinterpret either surface as a
  popover consumer.
- Deferred Sheet drag-to-dismiss as a Wave 2B-2 follow-on note in ROADMAP /
  CURRENT-STATE / NEXT-SESSION, without creating a new BACKLOG item.
- Routed future `.dialog::backdrop` visual styling changes to revisit native
  backdrop / external `.modal-scrim` layering.
- Kept BACKLOG #41, #44, #46, and #47 unchanged.

### Verified

- `wp-env run cli wp core version`: 7.0.
- 2 modules x desktop/mobile x light/dark: console 0 and overflow 0 in all 8
  cells.
- Dialog: 2 triggers, 2 native `dialog.dialog` hosts, 1 scrim, basic and
  full-screen open / close / focus restoration PASS.
- Dialog backdrop: real pointer outside the basic dialog hit the native dialog
  backdrop path (`scrimClicks=0`, `dialogBackdropClicks=1`); programmatic
  `.modal-scrim` click used the defensive scrim path; no double-fire observed.
- Sheet: 2 triggers, 2 `.sheet[role="dialog"]` hosts, 1 scrim, bottom and side
  open / close / focus containment / focus restoration PASS.
- Drag-to-dismiss defer note visible; no interactive drag runtime added.
- `node --check products/reference-implementations/axismundi-lab/modules/dialog/lab-dialog.js`:
  PASS.
- `node --check products/reference-implementations/axismundi-lab/modules/sheet/lab-sheet.js`:
  PASS.
- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS; Axis A/B/C/D/E/F/G all 1.000.
- `npm run validate:computed`: PASS.
- `npm run publish:styleguide`: PASS, with generated mirror restored after
  validation.
- `git diff --check`: PASS.

## v3.6.10 - Wave 2B-1 Form Controls (2026-05-22)

Input-domain component-lab release that closes the first Wave 2B slice:
Checkbox #18, Radio #19, and Switch #20. This cycle implements Route B, Form
Controls Core.

### Added

- Added the v3.6.10 Phase 0/1/2/3/5 docs under `docs/v3.6.10/`.
- Added `modules/checkbox/`, `modules/radio/`, and `modules/switch/` with
  lab-scoped CSS, pattern HTML, and SPEC/MEASUREMENT/WP audit docs.
- Added `lab-checkbox.js` as fixture-only indeterminate setup; no `lab-radio.js`
  or `lab-switch.js` was added.
- Promoted diagnostic-first to Lock 5 in `AGENTS.md` and `CLAUDE.md` after six
  clean diagnostic-first cycles.

### Routed

- Routed remaining Wave 2B work into Wave 2B-2 Dialog / Sheet, Wave 2B-3
  Date+Time #22+#23 PARTIAL completion, and Wave 2B-4 Actions consumers
  #5 / #7 / #8.
- Accepted `window.labCheckbox = { init }` as a small fixture
  re-initialization convention; no BACKLOG item was created.
- Kept BACKLOG #41 shared WordPress ripple runtime packaging unchanged.
- Kept BACKLOG #44 remaining specimen coverage / validator polish unchanged.
- Kept BACKLOG #46 disabled ripple host authoring hygiene unchanged.
- Kept BACKLOG #47 popover provider menu-item-class logic extraction hygiene
  unchanged.

### Verified

- `wp-env run cli wp core version`: 7.0.
- 3 modules x desktop/mobile x light/dark: console 0 and overflow 0 in all 12
  cells.
- Checkbox: 10 inputs, 2 error specimens, initial indeterminate state,
  disabled indeterminate, native indeterminate click transition, label click,
  Space toggle, and disabled no-toggle all PASS.
- Radio: 2 fieldsets, 2 legends, 6 inputs, 1 disabled input, same-name group
  exclusivity, label click, ArrowRight / ArrowLeft navigation, and disabled
  no-select all PASS.
- Switch: 6 `role="switch"` checkbox inputs, 2 disabled inputs, FormData
  participation, label click, Space toggle, second Space removal, and disabled
  no-toggle all PASS.
- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS; Axis A/B/C/D/E/F/G all 1.000.
- `npm run validate:computed`: PASS.
- `npm run publish:styleguide`: PASS, with generated mirror restored after
  validation.
- `git diff --check`: PASS.

## v3.6.9 - Wave 2A-2 Menu / Popover Consumer (2026-05-22)

Component-lab release that completes Wave 2A by closing BACKLOG #45. This
cycle implements Route A: Menu Consumer Closure, Provider Unchanged.

### Added

- Added the v3.6.9 Phase 0/1/2/3/5 docs under `docs/v3.6.9/`.
- Added `modules/menu/` with lab-scoped CSS, pattern HTML, and
  SPEC/MEASUREMENT/RUNTIME/WP audit docs.
- Added a live Menu pattern that consumes existing `popover/` and `ripple/`
  providers without adding `lab-menu.js`.
- Added BACKLOG #47 for future popover provider menu-item-class logic
  extraction hygiene.

### Routed

- Closed BACKLOG #45 and marked Wave 2A complete.
- Deferred interactive submenu runtime to a future provider-contract review.
- Kept BACKLOG #46 disabled ripple host authoring hygiene open by omitting
  `data-ax-ripple` from disabled Menu item hosts.
- Kept BACKLOG #41 shared WordPress ripple runtime packaging unchanged.
- Kept BACKLOG #44 remaining specimen coverage / validator polish unchanged.
- Kept diagnostic-first as a methodology finding, not Lock 5.

### Verified

- `wp-env run cli wp core version`: 7.0.
- 1 module x desktop/mobile x light/dark: console 0 and overflow 0 in all 4
  cells.
- Menu visual surfaces: 3 popover-wired surfaces, 1 static structure specimen,
  4 total `.ax-menu` surfaces.
- Menu interactions: trigger open, first enabled item focus, ArrowDown,
  ArrowUp, Home, End, Escape close/focus restore, outside pointerdown close,
  and item-click close all PASS.
- Forbidden `.prose` trigger: `aria-expanded=false`, `.is-open=false`,
  `visibility:hidden`, `opacity:0`.
- Ripple: 10 enabled bounded hosts, 2 disabled hosts, 0 disabled ripple hosts,
  `.ax-ripple` creation confirmed on enabled item click.
- Submenu: defer note visible, interactive submenu triggers 0.
- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS; Axis A/B/C/D/E/F/G all 1.000.
- `npm run validate:computed`: PASS.
- `npm run publish:styleguide`: PASS, with generated mirror restored after
  validation.
- `git diff --check`: PASS.

## v3.6.8 - Wave 2A Navigation Core (2026-05-22)

Component-lab release for the first Wave 2A navigation slice. This cycle
implements Route B: App bar, Nav bar, Nav rail, and Tabs, while deferring Menu
to Wave 2A-2 because Menu is DISTINCT but COUPLED with the popover provider.

### Added

- Added the v3.6.8 Phase 0/1/2/3/5 docs under `docs/v3.6.8/`.
- Added lab modules for App bar, Nav bar, Nav rail, and Tabs.
- Added Tabs component-local runtime for click, ArrowLeft/ArrowRight, Home/End,
  roving `tabindex`, `aria-selected`, and panel visibility.
- Added BACKLOG #45 for Wave 2A-2 Menu / popover consumer closure.
- Added BACKLOG #46 for disabled ripple host authoring hygiene.

### Routed

- Deferred Menu to Wave 2A-2 to preserve the Menu/popover DISTINCT but COUPLED
  boundary.
- Kept App bar action-slot ripple as CANDIDATE; no silent TARGET promotion.
- Kept BACKLOG #41 shared WordPress ripple runtime packaging unchanged.
- Kept BACKLOG #44 residual specimen coverage / validator polish unchanged.
- Kept diagnostic-first as a methodology finding, not Lock 5.

### Verified

- `wp-env run cli wp core version`: 7.0.
- 4 modules x desktop/mobile x light/dark: console 0, overflow 0 in all 16
  cells.
- Nav bar / Nav rail / Tabs: live bounded ripple provider attachment verified
  by `.ax-ripple` creation.
- App bar: `window.axRipple` undefined, host count 0, action-slot ripple still
  CANDIDATE.
- Tabs: click, ArrowLeft/ArrowRight, Home/End, disabled-skip, panel
  hidden/show, and per-tabset roving state PASS.
- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS; Axis A/B/C/D/E/F/G all 1.000.
- `npm run validate:computed`: PASS.
- `npm run publish:styleguide`: PASS, with generated mirror restored after
  validation.
- `git diff --check`: PASS.

## v3.6.7 - WP Specimen Follow-On Editor Compatibility (2026-05-21)

Split-fixture release for BACKLOG #44. This cycle closes the specimen editor
compatibility question by preserving the original front-end evidence wall and
adding a separate WordPress-save-compatible editor smoke surface.

### Added

- Added the v3.6.7 Phase 0/1/2/3/5 docs under `docs/v3.6.7/`.
- Added `core-block-editor-smoke.html` as an editor-valid core block smoke
  fixture.
- Extended `build_pilot_specimen_wall.py` to import both the original specimen
  wall and the editor smoke fixture.
- Extended `validate_pilot_specimen_wall.js` so `validate:specimen-wall`
  checks both the front-end wall and the editor smoke surface.

### Routed

- Narrowed BACKLOG #44 to mark/highlight, long-line code, deep pullquote,
  Material Symbols follow-on coverage, and validator hardening polish.
- Kept BACKLOG #41's shared WordPress ripple runtime packaging decision
  unchanged.
- Deferred validator polish notes: env-var login credentials, strict section
  count, less timing-sensitive editor wait, and generic tmp directory naming.

### Verified

- `wp-env run cli wp core version`: 7.0.
- Front-end wall: HTTP 200, console 0, overflow 0, Tier 1 11/11.
- Editor smoke front end: HTTP 200, console 0, overflow 0, sections 6, buttons
  5, searches 2.
- Editor smoke editor: iframe 1, console 0, block validation 0, invalid UI 0,
  recovery UI 0.
- Existing wall editor reference remains intentionally isolated at console 56 /
  block validation 56.
- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS; Axis A/B/C/D/E/F/G all 1.000.
- `npm run validate:computed`: PASS.
- `git diff --check`: PASS.

## v3.6.6 - WP Block Bridge Ripple / Editor State Parity (2026-05-21)

Diagnostic routing release for BACKLOG #41. This cycle closes the current
v3.6.x theme-bridge editor state parity question and narrows #41 to a future
shared WordPress ripple runtime packaging decision.

### Added

- Added the v3.6.6 Phase 0/1/2/3/5 docs under `docs/v3.6.6/`.
- Added front-end and editor state matrices for `core/button` hover, focus,
  pressed, disabled, and selected exposure.
- Added Route C evidence explaining why the Pilot ripple bridge remains
  Pilot-only rather than graduating to the existing Ripple v2 provider.

### Routed

- Kept BACKLOG #41 open only for the shared WordPress ripple runtime packaging
  decision.
- Closed BACKLOG #41's current editor-canvas state parity question for
  `core/button`: focus/disabled pass; hover/pressed/selected are not exposed
  theme targets in the editor canvas.
- Routed the editor block-validation console errors observed during Phase 1/3
  to BACKLOG #44 editor-valid fixture / editor compatibility work.

### Verified

- `wp-env run cli wp core version`: 7.0.
- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS; Axis A/B/C/D/E/F/G all 1.000.
- `npm run validate:computed`: PASS.
- `git diff --check`: PASS.

## v3.6.5 - WP Block Bridge Editor Token Parity (2026-05-21)

Editor token plumbing release for BACKLOG #41. This cycle closes the editor
md-sys color token enqueue parity item surfaced during v3.6.4 Phase 3 visual
QA.

### Added

- Added the v3.6.5 Phase 0/1/2/3/5 docs under `docs/v3.6.5/`.
- Added diagnostic-first root-cause evidence for editor iframe token loading.
- Added a TT5 reference note: TT5 remains a future core-block selector/schema
  reference, not a v3.6.5 implementation source.

### Changed

- Repaired the malformed trailing comment in `tokens.sys.light.css` across the
  lab, Pilot, and styleguide tracked copies.
- Restored WordPress 7.0 editor iframe resolution for md-sys light color
  tokens used by block bridge surfaces.

### Routed

- Closed BACKLOG #41's editor md-sys color token enqueue parity item.
- Kept BACKLOG #41 open for ripple bridge graduation and broader editor-canvas
  state parity.
- Kept BACKLOG #44 as owner of the pre-existing editor-invalid-content /
  editor-valid fixture work.

### Verified

- `wp-env run cli wp core version`: 7.0.
- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS.
- `npm run validate:computed`: PASS.
- `git diff --check`: PASS.

## v3.6.4 - WP Block Bridge Residual Cleanup (2026-05-21)

Mechanical cleanup release for BACKLOG #41 residual work after the v3.6.3
semantic routes. This cycle enforces Lock 3 and Lock 4 without reopening the
semantic decisions.

### Added

- Added the v3.6.4 Phase 0/1/2/3/5 docs under `docs/v3.6.4/`.
- Added editor canvas and front-end drag console smoke evidence to the Phase 3
  visual QA record.

### Changed

- Cleaned up `core/button` link affordances after the v3.6.3 semantic route:
  removed post-content underline leakage, disabled text selection, and
  preserved focus/hover/pressed state behavior.
- Narrowed quote bridge selectors so `core/pullquote` no longer silently
  absorbs `core/quote` blockquote styling.
- Added distinct `core/pullquote` bridge CSS following the lab pullquote route:
  centered editorial surface, top/bottom dividers, headline-medium italic
  paragraph, and body-small citation styling.

### Routed

- Reduced BACKLOG #41 residual scope to ripple/editor parity questions after
  closing button mechanical cleanup and quote/pullquote distinct-surface work.
- Routed the editor canvas sys-color token enqueue gap to existing #41 editor
  parity / #44 editor compatibility territory; it is pre-existing token
  plumbing, not a v3.6.4 regression.
- Documented the user-observed `content.js` drag console error as not
  reproduced in an extension-free browser and likely extension/content-script
  noise unless it reproduces in the tracked Pilot bundle.

### Verified

- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS.
- `npm run validate:computed`: PASS.
- `git diff --check`: PASS.

## v3.6.3 - WP Block Bridge Expansion (2026-05-20)

Implementation/routing release for the first BACKLOG #41 slice. This cycle
consumes the v3.6.2 specimen wall evidence, fixes one reset leak, bridges three
core block surfaces, and routes two semantic decisions without implementing
custom blocks.

### Added

- Added the v3.6.3 Phase 0/1/2/3/5 docs under `docs/v3.6.3/`.
- Added `.gitattributes` line-ending policy so text files check out as LF while
  Windows command scripts keep CRLF.

### Changed

- Reset `core/table` footer borders so `tfoot` no longer inherits the native
  3px currentColor rule.
- Bridged `core/search.is-style-filled-search` toward the lab Search bar
  surface.
- Bridged long-line overflow for `core/code`, `core/preformatted`, and
  post-content `pre`.
- Bridged `core/separator` default, wide, dots, inset, and middle-inset
  variants with token-routed visibility.
- Corrected the BACKLOG #41 / #44 / #14 separator-to-Material-Symbols
  cross-link; separator visibility is bridge CSS/style-variation work unless
  later evidence proves a real font dependency.

### Routed

- Routed `button-anchor-semantics`: `core/button` anchors with `href` remain
  valid navigation and may receive an M3 button visual bridge; action behavior
  stays plugin/custom-block territory.
- Routed `quote-pullquote-semantics`: `core/quote` and `core/pullquote` remain
  distinct theme-owned surfaces; future CSS must not silently collapse them.
- Promoted two lesson locks: core/button semantic route before visual cleanup,
  and semantic mismatch handling before accepting visual fixes.

### Verified

- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS.
- `npm run validate:computed`: PASS.
- `git diff --check`: PASS.

## v3.6.2 — WP Core Block Specimen Wall (2026-05-20)

Evidence collection / classification release for BACKLOG #43. This cycle
creates the Tier 1 WordPress core block specimen wall and routes findings into
BACKLOG #41 without implementing bridge/reset fixes.

### Added

- Added a version-controlled specimen fixture for 11 Tier 1 WordPress core block
  families.
- Added an idempotent importer:
  `tools/generators/build_pilot_specimen_wall.py`.
- Added `npm run validate:specimen-wall` through
  `tools/validators/validate_pilot_specimen_wall.js`.
- Added Phase 1/2/3/5 evidence docs under `docs/v3.6.2/`.

### Classified

- Verified 11 / 11 Tier 1 families and 26 / 26 classified entries with 0
  unclassified entries.
- Phase 2 computed bucket distribution:
  no-action 20, reset 1, bridge 0, semantic-decision 5, backlog 0.
- Phase 3 visual QA added 10 routed findings:
  backlog 3, reset 1, semantic-decision 2, bridge 3, no-action 1.

### Routed

- Closed BACKLOG #43 as a Tier 1 evidence cycle.
- Routed table footer reset, search/code/separator bridge inputs, and
  button/quote semantic decisions to BACKLOG #41.
- Added BACKLOG #44 for specimen follow-on coverage and editor compatibility.
- Kept the current Tier 1 fixture fixed; mark/highlight, long-line expansion,
  editor validity, and deeper pullquote coverage move to follow-on work.

### Verified

- `python tools/generators/build_pilot_specimen_wall.py`: PASS.
- `npm run validate:specimen-wall`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS.
- `npm run validate:computed`: PASS.
- `git diff --check`: PASS.

## v3.6.1 — Token Architecture Refactor (2026-05-20)

Cross-cutting lab + Pilot token architecture release. This closes BACKLOG #42
and the deferred BACKLOG #20 theme-only color policy by making the M3 token
graph explicit, downstream-only, and validator-backed.

### Added — Token layers

- Split the former token entry point into explicit layers:
  `tokens.ref.css`, `tokens.sys.light.css`, `tokens.sys.dark.css`, and
  `tokens.comp.css`.
- Kept `tokens.css` as an empty compatibility shim with no residual token
  definitions and no import chain.
- Loaded tokens in the validated order:
  `ref -> sys.light -> comp -> sys.dark -> wp-preset -> wp-custom -> shim`.
- Updated the lab styleguide, Pilot asset bridge, and generated mirror to carry
  the new layer files.

### Added — WordPress projections

- Added `wp-preset.bridge.css` for editor-facing semantic color projections.
- Added `wp-custom.bridge.css` for state-layer, shape, motion, elevation, and
  component-default projections.
- Added `theme.json settings.custom.axismundi.*` with 26 downstream-only
  `var(...)` leaves.
- Preserved `settings.color.custom = false` as the theme-only default while
  keeping WordPress registered values connected to M3 tokens.

### Added — Dark mode infrastructure

- Added Pilot Light / Dark / Auto controls and runtime bridge.
- Implemented dark mode as sys-layer remapping only: `md-ref` primitives remain
  unchanged while `md-sys` roles swap their ref-tone mappings.
- Extended computed validation to cover forced light/dark front surfaces,
  forced light/dark styleguide block surfaces, and the real Pilot click path.

### Added — Validator locks

- Added Axis E: every `--md-sys-color-*` must resolve through
  `var(--md-ref-palette-*)`; literal md-sys color values fail validation.
- Added Axis F: WordPress bridge entries must be single-var downstream
  projections with existing upstream token references.
- Added Axis G: `theme.json settings.custom.axismundi.*` must use downstream
  `var(--comp-*)`, `var(--md-sys-*)`, or `var(--md-ref-*)` values.
- Locked the two architectural lessons in AGENTS / CLAUDE, PRE-ENTRY,
  FEEDBACK-AND-STRATEGY, and NEXT-SESSION:
  `wp-custom` is never a source, and `md-sys` colors map to `md-ref`.

### Routed

- Closed BACKLOG #20.
- Closed BACKLOG #42.
- Routed Phase 3 visual QA findings to BACKLOG #43 / #41:
  table footer native border strength and the core/button semantic boundary
  between `<button>` styleguide specimens and WordPress link-based core/button
  markup.
- Deferred Axis H bridge/theme correspondence and auto-mode media emulation as
  non-blocking future candidates.

### Verified

- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `npm test`: PASS; validator overall 1.000 PASS with Axis E/F/G at 1.000.
- `npm run validate:computed`: PASS; light/dark matrix and Pilot click path
  verified.
- Phase 3 visual QA: PASS with two routed non-blocking findings.

## v3.6.0 — Ontology Theme Pilot v0 (2026-05-19)

First real WordPress block theme Pilot. This is a theme-only proof, not the
final distributable theme: it validates scaffold, asset bridge, templates,
patterns, Font Library registration, and a minimum WordPress core block -> M3
reverse mapping bridge.

### Added — Pilot theme

- Created `products/reference-implementations/axismundi-pilot/` with
  `style.css`, `theme.json`, `functions.php`, templates, parts, patterns, and
  draft WP.org support files.
- Added root `.wp-env.json`; verified activation in WordPress 6.9.4.
- Built an asset bridge generator that copies lab CSS, fonts, and icon assets
  into the Pilot so the theme is self-contained.
- Registered five Pilot patterns: hero, button actions, card list,
  search section, and prose sample.
- Registered core-block styles for Button, Group/Card, List, Search, and
  Separator without registering custom blocks.

### Added — WordPress/M3 bridge

- Added a Pilot-specific block bridge mapping WordPress core output back to M3
  tokens and interaction contracts.
- Mapped native WordPress Button `fill` / `outline` to M3 Filled / Outlined and
  registered only the missing `tonal`, `elevated`, and `text` styles.
- Added bounded ripple attachment and finite radius morphing for core/button
  links while preserving WordPress core anchor semantics.
- Mapped post-content prose block-by-block instead of forcing a `.prose`
  wrapper, preserving per-block customization.
- Reset and re-mapped core table, search, code, separator, quote, and list
  defaults where WordPress core styles leaked through.

### Added — Verification

- Added `npm run validate:computed` to verify rendered computed styles rather
  than selector presence.
- Computed QA now covers Button, Search, Code, Quote, Separator, default Table,
  Stripes Table, overflow, console/page errors, and the static styleguide block
  table specimen.
- Verified Korean prose rendering and the Roboto/Noto fallback chain.
- Registered Roboto Flex, Noto Sans KR, Roboto Serif, Noto Serif KR, and Roboto
  Mono in the WordPress Font Library; kept Material Symbols as chrome-only font.

### Changed — Architecture

- Locked the v3.6.0 narrative as:
  `Pilot v0 — scaffold + Wave 1 reverse mapping + block bridge MVP`.
- Documented the build-direction reversal:
  design-system construction is M3-forward; WordPress theme integration is
  CMS-first reverse mapping.
- Documented the WP core reset-first rule: inventory -> reset -> M3 mapping ->
  interaction -> computed audit.
- Added `docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md` as the
  authoritative input for v3.6.1.
- Refined `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md` with the
  `md-ref -> md-sys -> wp-preset/wp-custom -> ax-comp` model.

### Routed

- BACKLOG #20 remains partially validated; final close moves to v3.6.1 token
  architecture refactor.
- BACKLOG #21 now explicitly owns the reverse/customizable Interpreter Plugin
  direction.
- Added BACKLOG #42 for the v3.6.1 Token Architecture Refactor.
- Added BACKLOG #43 for a full WP core block specimen wall / variation audit.
- BACKLOG #41 continues to own full block bridge expansion.

### Verified

- Validator: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- `npm test`: PASS.
- `npm run validate:computed`: PASS.
- `php -l products/reference-implementations/axismundi-pilot/functions.php`:
  PASS.
- `wp-env`: Pilot active and front-end/editor smoke tested.

## v3.5.18 — Pre-Pilot Cleanup + Carousel Reroute (2026-05-18)

Small pre-Pilot cleanup release before v3.6.0. No component matrix rows changed;
distribution remains 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD. Carousel keeps
its historical v3.5.12 closure while being explicitly excluded from the
theme-only Pilot and routed to plugin territory.

### Changed — Carousel routing

- Amended Matrix row #34 without rewriting history: Carousel remains DONE
  evidence from v3.5.12, but is now `Pilot-excluded / plugin-routed`.
- Retained `lab/modules/carousel/` as the seed evidence for future plugin
  extraction.
- Added BACKLOG #38 for Carousel plugin/block extraction after v3.6.0 Pilot
  entry.

### Changed — Process discipline

- Added `User Request Log — Do Not Abstract Away` to AGENTS.md and CLAUDE.md.
- Added the global portal/overlay smoke-test rule: trigger, runtime, host,
  contract, and errors must all be checked before Phase 3 PASS.
- Recorded the v3.5.16 request-loss lesson and the v3.5.17 `#sg-portal`
  regression in `PRE-ENTRY-ONTOLOGY-GROUNDING.md`.

### Changed — Pilot inputs

- Reclassified `blocks.html` and `prose.html` as v3.6.0 Pilot specification
  surfaces, not cosmetic documentation pages.
- Added `docs/v3.5.18/BLOCKS-PROSE-PILOT-SPEC-VERIFY.md`.
- Added `docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md` for theme-only Pilot
  entry.

### Fixed — Prose mobile spec surface

- Fixed `prose.html` 390px overflow by adding layout min-width guards to the
  source `style-guide-prose.html` and regenerating `/styleguide/prose.html`.
- Routed remaining `sg-sidebar` mobile shell inconsistency for blocks/prose to
  BACKLOG #39 instead of blocking Pilot entry.

### Verified

- Validator: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- `npm test`: PASS.
- `publish_styleguide.py`: regenerated `/styleguide/`.
- Pre-Pilot smoke checklist passed for styleguide index, blocks, prose,
  typography-axis, and all current lab pattern pages.

## v3.5.17 — Styleguide Shell Rebuild + Mobile Reading Polish (2026-05-18)

Corrective styleguide UX release after v3.5.16 closed the framing work but did
not yet satisfy the requested mobile shell and reading experience. No component
matrix rows changed; distribution remains 13 DONE / 2 PARTIAL / 16 TODO /
3 RECORD.

### Changed — Styleguide-local shell

- Rebuilt the styleguide shell as **styleguide-local chrome**, not as App bar,
  Navigation drawer, or Sheet component completion.
- Added a mobile top app bar with a real icon-button menu trigger.
- Added `.sg-drawer`, a Sheet-style side drawer using `.sg-*` naming only; no
  `.ax-app-bar`, `.ax-nav-drawer`, or `.ax-sheet` promotion claim.
- Drawer content is cloned from the canonical sidebar navigation so desktop and
  mobile nav stay in the same order.
- Preserved the desktop/tablet sidebar at 768px and above.

### Changed — Theme and body reading

- Converted the styleguide theme switcher to icon buttons:
  `light_mode`, `dark_mode`, and `contrast`.
- Preserved the existing `data-theme-button` contract so lab/module pages can
  continue sharing the same theme runtime.
- Added mobile reading polish: compact/wrapping palette chips and native
  `<details><summary>` read-more disclosure for long explanatory copy.
- Updated styleguide version display to:
  `Axismundi Style Guide v0.3.0` with `Monorepo cycle: v3.5.17`.

### Changed — Typography adjunct

- Added `typography-axis.html` as a Foundation > Typography adjunct link, per
  BACKLOG #11's prior decision that the axis specimen is not a standalone
  module entry.
- Updated `publish_styleguide.py` so generated `/styleguide/` links point to
  the repository-root typography-axis specimen.
- Optimized `typography-axis.html` for mobile reading and control use:
  responsive typography, read-more framing, sticky axis controls, and a
  collapsible `Axis controls` panel so the controls do not cover the specimen.

### Fixed — Live Dialog / Sheet portal

- Restored the source `#sg-portal` live modal host required by the Dialog and
  Sheet buttons in the styleguide.
- Regenerated `/styleguide/` so GitHub Pages includes:
  `#sg-modal-basic`, `#sg-modal-full`, `#sg-sheet-bottom`, and
  `#sg-sheet-side`.
- Hardened `style-guide.js` with a checkbox null guard and native
  `HTMLDialogElement.showModal()` / `.close()` handling for dialog demos.

### Deferred

- Rich motion and full docs-site dogfooding remain deferred to BACKLOG #37,
  after Wave 2 navigation components are closed.
- BACKLOG #34 remains partially open for the later N3 module picker/dialog UX.

### Verified

- Validator: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- `npm test`: PASS.
- `publish_styleguide.py`: regenerated `/styleguide/` successfully.
- Phase 3 acceptance checks passed for mobile top bar, drawer toggle, icon
  theme switcher, body/nav order, lab links, validation banners, typography
  adjunct link, and the v0.3.0 / v3.5.17 version display.
- User visual QA passed for the updated `typography-axis.html` collapsible
  controls.

## v3.5.16 — Styleguide Modernization + Module Workspace Framing (2026-05-18)

Post-publish public surface modernization. No component matrix rows changed;
distribution remains 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD.

### Changed — Public surface framing

- Amended `PUBLIC-SURFACE-CHARTER.md §3.3`: `lab/modules/*` is now framed as
  a module workspace + validation specimen surface. `/styleguide/` remains the
  canonical public visual demo; lab pattern pages are browsable evidence, not
  public API.
- Updated `lab/modules/README.md` to distinguish styleguide mirror assets from
  repository-root GitHub Pages browsing.
- Kept v4.0 directory restructure deferred under BACKLOG #36.

### Changed — Styleguide navigation

- Added 18 styleguide actions:
  - 15 `Validation specimen` links for Wave 1 / legacy component modules.
  - 3 `Record audit` links for Avatar, Divider, and Badge.
- Converted these actions to Material Symbols icon+label links.
- Added mobile-first shell guardrails to root `index.html` and
  `style-guide.html`.
- Added a GitHub repository link to the root Pages entry.
- Phase 3 follow-up locked the ordering rule: **sidebar nav is the canonical
  source of section order; body sections follow nav order**. The 37 body
  sections now match the 37 sidebar anchors exactly.

### Changed — Publish mirror

- `publish_styleguide.py` now copies `theme.js` into `/styleguide/scripts/`,
  closing BACKLOG #13 and removing the publish-surface 404.
- Generated `/styleguide/index.html` rewrites lab links from source-local
  `modules/...` paths to repository-root Pages paths under
  `../products/reference-implementations/axismundi-lab/modules/...`.
- Publish mirror regenerated: 31 files, including `scripts/theme.js`.

### Changed — Lab specimens and hygiene

- Added `Validation specimen` banners to all 16 current `lab-*-pattern.html`
  pages.
- Closed or clarified BACKLOG items:
  - #1 helper inline-code font-size inheritance fixed.
  - #10 resolved by v3.5.6 Ripple v2.
  - #11 framework portion resolved by v3.5.0; remaining UX superseded by #34.
  - #13 resolved by v3.5.16 `theme.js` publish copy.
  - #17 resolved by v3.5.7 Text field + v3.5.8 Search bar.
  - #28 icon button SVG-era public wording fixed.
- `MODULE-STATUS-MATRIX.md` now marks Snackbar and Tooltip as legacy DONE rows
  whose audit shape predates the v3.5.0 3/4-doc framework.

### Verified

- Validator: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- `npm test`: PASS.
- Playwright checks:
  - root / styleguide / lab pattern pages at 390 / 768 / 1280: overflow 0.
  - styleguide lab links: 18.
  - styleguide icon links: 18.
  - nav anchors: 37; body sections: 37; nav/body order equality: true.

## v3.5.15 — GitHub Repository + Pages Publish (2026-05-17)

First public GitHub publish cycle.

### Published

- Local workspace renamed from
  `C:\Users\thaum\dev\axismundi-v3.5.1-phase0-handoff` to
  `C:\Users\thaum\dev\axismundi`.
- Initialized git history with a single root commit:
  `e22b9e5 Initial Axismundi public release`.
- Created and pushed repository:
  `https://github.com/Jiwoon-Kim/axismundi`.
- Enabled GitHub Pages from `main` branch root.
- Public Pages URL:
  `https://jiwoon-kim.github.io/axismundi/`.

### Verified

- Post-rename validator: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- `npm test`: PASS.
- `publish_styleguide.py`: PASS.
- GitHub Pages HTTP checks: root, styleguide, README, README.ko, lab overview,
  lab module index, templates note, LICENSE-MATRIX, and NOTICE all returned
  200.
- Root page renders `Axismundi`, links to `/styleguide/`, and keeps author
  metadata aligned as `KIM JIWOON (designbusan.ai.kr) — Busan, Korea`.

### Deferred

- v3.5.16 modernization remains next: styleguide/module navigation UX,
  validation-specimen framing, BACKLOG hygiene, and "lab" naming amendment.
- `index.ko.html` / language toggle remains BACKLOG #35.
- v4.0 directory restructure remains deferred.

## v3.5.14 — Publish Prep (2026-05-17)

Repository publish-prep release after Wave 1 closure. No component matrix rows
change; distribution remains 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD.

### Added — License and public metadata

- Added root `LICENSE` with GPL-3.0 text for code/theme/tooling.
- Added `LICENSE-CC-BY-SA-4.0.md` for documentation and ontology/data content.
- Updated `LICENSE-MATRIX.md` with Code vs Content boundaries, GPL/CC
  compatibility notes, and WordPress.org submission compatibility framing.
- Updated `NOTICE.md` with current asset paths under
  `core/design-systems/material3/assets/`.
- Aligned `package.json` / `package-lock.json` metadata to `axismundi`,
  `GPL-3.0-or-later`, and the public project description.

### Added — Publish prep surfaces

- Rewrote `README.md` and added `README.ko.md`.
- Added `.github/workflows/validator.yml` for validator-only CI on PRs, pushes
  to `main`, and manual dispatch.
- Updated `.gitignore` for Node, Playwright, temporary, editor, and OS
  artifacts while keeping validator evidence tracked.
- Added `docs/v3.5.14/TEMPLATES-PUBLISH-CATEGORY-NOTE.md` to define the future
  `/templates/` publish category as a lab composition / page-layout preview
  layer, not a replacement for `/styleguide/`.
- Refreshed root `index.html` as the public entry point with current
  styleguide, lab module, template-note, license, and author links.

### Changed — Publish mirror and path readiness

- `tools/generators/publish_styleguide.py` wording now describes the
  `axismundi-lab` source instead of the old RC theme.
- `publish_styleguide.py` regenerated the styleguide mirror: 30 files and 16
  module CSS files.
- Public-facing stale path references were cleaned where active; historical
  provenance paths remain intentionally preserved.
- Authorship metadata is aligned as `KIM JIWOON (designbusan.ai.kr) — Busan,
  Korea`.

### Deferred

- GitHub repository creation, local directory rename, and GitHub Pages
  activation remain deferred to v3.5.15.
- Playwright CI remains deferred until the local smoke scripts are stable.
- Template implementation remains deferred; v3.5.14 records the category only.

Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS. `npm test` PASS.

## v3.5.13 — Wave 1 Closure Cleanup (#32 + #33 + Records) (2026-05-17)

Post-Wave-1 cleanup release. No new component row closes; Wave 1 remains 9 / 9
complete and the matrix distribution remains 13 DONE / 2 PARTIAL / 16 TODO /
3 RECORD.

### Closed — BACKLOG #32 Button family size variants

- Added Button family size tokens to `tokens.css`.
- Added opt-in `is-size-xs/s/m/l/xl` hooks for Button, Icon button, and Button
  group in `components.css`.
- Playwright verified:
  - Button and Icon button XS/S/M/L/XL = 32 / 40 / 56 / 96 / 136.
  - Button group standard spacing = 18 / 12 / 8 / 8 / 8.
  - Button group connected gap = 2px and XS/S min-width = 48px.
- Default no-size Button remains 40px for Wave 1 regression safety.
- Card composition QA surfaced the Icon button finite-radius tail from #31;
  v3.5.13 removes the `9999px -> 8px` interpolation path for composed Icon
  buttons.

### Closed — BACKLOG #33 List M3 full token coverage

- Extended `LIST-SPEC-AUDIT.md` and `LIST-MEASUREMENT-AUDIT.md` with the
  full-token classification.
- Patched `components.css` §26 only:
  - focus indicator now resolves to 3px / -3px;
  - selected-disabled container resolves to 38% on-surface mix;
  - segmented wrapper remains transparent / radius 0 / padding 0;
  - segmented item containers own `surface` and `corner-large`;
  - List Expand trailing icon container maps to `surface-container`
    (`#211f26` in dark scheme);
  - trailing supporting time text no-wraps.
- Expand/collapse runtime, drag/reorder runtime, video slot, and generic §0
  state-layer rewrites remain deferred.

### Added — Baseline-only Record sweep

- `products/reference-implementations/axismundi-lab/modules/_records/AVATAR-RECORD-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/_records/DIVIDER-RECORD-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/_records/BADGE-RECORD-AUDIT.md`

Avatar, Divider, and Badge retain RECORD status. The record-only docs document
the honest "baseline is enough" outcome; they do not promote these rows to DONE
component modules.

### Changed — Cleanup phase docs

- `docs/v3.5.13/WAVE-1-CLEANUP-PHASE-0-PLAN.md`
- `docs/v3.5.13/WAVE-1-CLEANUP-PHASE-0-REPORT.md`
- `docs/v3.5.13/WAVE-1-CLEANUP-PHASE-1-PLAN.md`
- `docs/v3.5.13/WAVE-1-CLEANUP-PHASE-2-PLAN.md`
- `docs/v3.5.13/BUTTON-FAMILY-SIZE-AUDIT.md`

Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS.

## v3.5.12 — Wave 1 — Carousel #34 (2026-05-17)

Ninth Wave 1 closure and final Wave 1 entry. Carousel closes as the second
4-doc dual-category component after Search bar because it owns extracted
JavaScript runtime from the v3.3.2 carousel module.

```txt
Wave 1 progress: 8 / 9 -> 9 / 9 COMPLETE
Matrix:          12 DONE / 3 PARTIAL -> 13 DONE / 2 PARTIAL
```

### Added — Carousel phase docs

- `docs/v3.5.12/CAROUSEL-PHASE-0-PLAN.md`
- `docs/v3.5.12/CAROUSEL-PHASE-0-REPORT.md`
- `docs/v3.5.12/CAROUSEL-PHASE-2-PLAN.md`

### Added — Carousel 4-doc audit set

- `products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-MEASUREMENT-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-WP-MAPPING.md`
- `products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-RUNTIME-AUDIT.md`

The audit records:

- Carousel is Component Full-Spec + Interaction.
- `lab-carousel.js` is component-owned runtime evidence, not demo-only.
- 4-doc shape is required; RUNTIME-AUDIT owns G11-G16.
- `.ax-carousel` is canonical; `.ax-material-slider` remains a historical
  lab-runtime adapter selector.
- Gallery is not Carousel; WordPress mapping remains conditional only.

### Changed — Carousel lab runtime

- `lab-carousel.css` now includes lab-scoped `prefers-reduced-motion: reduce`
  handling that disables transitions/animations and collapses `will-change`.
- `lab-carousel.js` now tracks reduced-motion state with `matchMedia`.
- `lab-carousel.js` now supports `Home` and `End` keyboard navigation.
- Runtime enhancement is now marked with `.is-enhanced`; without JavaScript,
  the lab carousel remains a native overflow-x scroll-snap rail.
- `lab-carousel-pattern.html` was refreshed to v3.5.12 copy, clarifies the
  Gallery boundary / Show all accessibility affordance, and replaces inline
  SVG prev/next chevrons with Material Symbols.

### Phase 3 visual QA

User + Playwright QA confirmed:

```txt
- 390 / 768 / 1280 viewport overflowX: 0
- End -> last dot; Home -> first dot
- ArrowRight / ArrowLeft preserved
- reduced-motion emulation: transition-property none on runtime targets
- no-JS fallback: overflow 0, .is-enhanced false, track overflow-x auto,
  scroll-snap x mandatory
- baseline components.css/tokens.css/style-guide.html/blocks.css untouched
```

### Changed — Matrix bookkeeping

- Row #34 Carousel: PARTIAL → **DONE** (v3.5.12).
- §6 Status distribution: 12 DONE / 3 PARTIAL → 13 DONE / 2 PARTIAL.
- Wave 1 is now **9 / 9 complete**.

Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS.

## v3.5.11 — Wave 1 — List #33 (2026-05-17)

Eighth Wave 1 closure and first Display-family Full-Spec component after Card.
List closes as a 3-doc Component Full-Spec trio with no runtime audit, keeping
static rows, action rows, navigation rows, and selectable row guidance distinct.

```txt
Wave 1 progress: 7 / 9 -> 8 / 9
Matrix:          11 DONE / 17 TODO -> 12 DONE / 16 TODO
```

### Added — List phase docs

- `docs/v3.5.11/LIST-PHASE-0-PLAN.md`
- `docs/v3.5.11/LIST-PHASE-0-REPORT.md`
- `docs/v3.5.11/LIST-PHASE-2-PLAN.md`
- `docs/v3.5.11/LIST-PHASE-5-PLAN.md`

### Added — List audit trio

- `products/reference-implementations/axismundi-lab/modules/list/docs/LIST-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/list/docs/LIST-MEASUREMENT-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/list/docs/LIST-WP-MAPPING.md`

The audit records:

- List is Component Full-Spec, 3-doc trio, no RUNTIME audit.
- Avatar #32 remains a Baseline-only Record composition dependency; it is not
  folded into List.
- Static informational rows have ripple NONE; interactive action/navigation
  rows promote to ripple TARGET bounded per item.
- `button role="listitem"` is documented as a baseline/style-guide risk and is
  not canonicalized.
- `core/list` is a partial static-editorial WordPress mapping only.

### Added — List lab artifacts

- `products/reference-implementations/axismundi-lab/modules/list/lab-list.css`
- `products/reference-implementations/axismundi-lab/modules/list/lab-list-pattern.html`

No `lab-list.js` was created. Expand, drag/reorder, virtualization, and managed
selection remain out of scope.

### Changed — Baseline List color alignment

`components.css` §26 received a small in-cycle List-only color patch after
token-level M3 review:

- segmented list container color now uses `md.sys.color.surface`,
- unselected direct trailing icons now use `md.sys.color.on-surface`,
- selected and disabled direct trailing icon overrides preserve existing
  selected/disabled state colors.

No BACKLOG #33 was opened because the mismatch was List-specific and the patch
stayed inside §26. `tokens.css`, `style-guide.html`, `blocks.css`, and
`theme.json` were not edited.

### Phase 3 visual QA

User + Playwright QA confirmed:

```txt
- 390 / 768 / 1280 viewport overflowX: 0
- Row heights: 56px / 72px / 88px
- Segmented gap: 2px
- Trailing time text: no minute-level wrapping
- Leading/trailing icon size: 24px
- Leading image slot: 56px
- Ripple hosts: item-only; container/static rows count 0
- M3 token-level color mismatch closed in-cycle
```

### Changed — Matrix bookkeeping

- Row #33 List: TODO → **DONE** (v3.5.11).
- §6 Status distribution: 11 DONE / 17 TODO → 12 DONE / 16 TODO.
- Row #36 ripple/ consumer-state sub-table: interactive List rows moved from
  CANDIDATE to TARGET bounded; static List rows remain NONE.
- §7 Dependency snapshot updated for List.

Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS.

## v3.5.10 — Wave 1 — Button Group #6 (2026-05-17)

Seventh Wave 1 closure and first Actions family entry to land under the
v3.5.9 finite pill baseline. Button group enters Component Full-Spec as a
3-doc trio (no RUNTIME audit), inheriting the connected pill morph contract
already corrected at v3.5.9.

```txt
Wave 1 progress: 6 / 9 -> 7 / 9
Matrix:          10 DONE / 18 TODO -> 11 DONE / 17 TODO
```

### Added — Button group phase docs

- `docs/v3.5.10/BUTTON-GROUP-PHASE-0-PLAN.md`
- `docs/v3.5.10/BUTTON-GROUP-PHASE-0-REPORT.md`
- `docs/v3.5.10/BUTTON-GROUP-PHASE-2-PLAN.md`

### Added — Button group audit trio

- `products/reference-implementations/axismundi-lab/modules/button-group/docs/BUTTON-GROUP-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/button-group/docs/BUTTON-GROUP-MEASUREMENT-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/button-group/docs/BUTTON-GROUP-WP-MAPPING.md`

The audit records:

- Button group is a single matrix row (not a family-merge). Split button #7 stays
  deferred and remains a future component cycle.
- Pattern A radio + label single-select and Pattern B button + aria-pressed
  multi-toggle are both supported as valid markup patterns.
- M3 web accessibility guidance (Tab / Space / Enter) and native radio
  arrow-key semantics are documented as compatible tensions — Pattern A is
  intentionally native and does NOT polyfill browser radio behavior.
- Ripple #36 row sub-table promoted Button group from CANDIDATE to TARGET
  bounded per segment. Group container has no ripple.
- icon-system/ is CURRENT conditional (Material Symbol when an icon segment
  is used).
- v3.5.9 finite pill morph contract is inherited verbatim; outer 20px / inner
  4px geometry verified.
- WordPress `core/buttons` is a partial visual mapping only. Filtering /
  sorting / view switching behavior remains plugin territory.

### Added — Button group lab artifacts

- `products/reference-implementations/axismundi-lab/modules/button-group/lab-button-group.css`
- `products/reference-implementations/axismundi-lab/modules/button-group/lab-button-group.js`
- `products/reference-implementations/axismundi-lab/modules/button-group/lab-button-group-pattern.html`

The lab JS file is a Pattern B aria-pressed demo only; it is NOT a public
runtime extraction. The audit trio remains 3-doc (no RUNTIME-AUDIT).

### Changed — Matrix bookkeeping

- Row #6 Button group: TODO → **DONE** (v3.5.10).
- §6 Status distribution: 10 DONE / 18 TODO → 11 DONE / 17 TODO.
- Row #36 ripple/ consumer-state sub-table: Button group moved from CANDIDATE
  to TARGET bounded per segment.
- §7 Dependency snapshot updated for Button group.

### Phase 3 visual QA — honest findings

User + Playwright QA confirmed:

```txt
- 390 / 768 / 1280 viewport overflowX: 0
- Group container data-ax-ripple count: 0
- Segment ripple host count: 28 + 28 (Pattern A label + Pattern B button)
- Connected selected segment morph: rest 20px -> active inner 4px,
  outer first/last corners preserved at 20px
- Pattern A radio ArrowLeft / ArrowRight changes checked state
- Pattern B Space / Enter toggles aria-pressed
- aria-disabled segments are guarded against toggle by the demo JS
- Reduced motion: transitions absent, layout stable
- Wave 1 smoke (Button / Icon button / FAB / Text field / Search bar):
  no mobile overflow regression
```

In-cycle honest findings:

```txt
- Pattern A Home / End does not change checked state in Chrome native radio.
  Acceptable: Pattern A is locked to native/no-JS and does NOT polyfill.
- M3 XS / S / M / L / XL size variants are NOT actually applied. The
  is-size-xs/s/l/xl baseline §28 hooks only adjust gap, min-inline-size, and
  connected inner corner radius. Segment height stays 40px because the
  Button family ships only the default M size. Pattern HTML §6 specimen is
  labelled "Size hooks — partial baseline" with a visible warning.
- SC 2.5.8 AA target size: PASS (40 >= 24).
- SC 2.5.5 AAA target size: honest NOT PASS for default M (40 < 44),
  matching the Button #1 target-size precedent.
```

### Added — BACKLOG entry

- **BACKLOG #32 — Button family size variants — XS/S/M/L/XL coverage cycle.**
  Cross-cutting size cycle affecting Button #1, Icon button #2, and Button
  group #6. Cycle shape similar to v3.5.9 pill-radius correction (foundation
  correction, not a Wave 1 component cycle).

### Validation

- Validator preserved: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- Edited bookkeeping files: CHANGELOG / ROADMAP / MODULE-STATUS-MATRIX /
  BACKLOG / CURRENT-STATE / NEXT-SESSION plus three Button group audit docs.
- Untouched baseline: `tokens.css`, `components.css`, `style-guide.html`,
  `blocks.css`, `theme.json`.

## v3.5.9 — Baseline Correction — Pill Radius Interpolation (#31) (2026-05-17)

Foundation cleanup release closing BACKLOG #31. This is **not** a Wave 1
component closure; Wave 1 remains 6/9. The release fixes the visible
`corner-full -> corner-small` interpolation flicker found by Playwright and
user visual QA during the Text field cycle.

### Changed — Morphing-safe pill radius contract

- Preserved `--md-sys-shape-corner-full: 9999px` for static pill semantics.
- Added `--md-sys-shape-corner-pill-stable: 50%` as the semantic marker for
  animation-safe pill surfaces.
- Changed Button's component radius token to a finite height-derived pill:
  `--comp-button-radius: calc(var(--comp-button-height) / 2)`.
- Added a Button group local variable in `components.css §28`:
  `--_button-group-pill-radius: calc(var(--comp-button-height) / 2)`.

### Changed — Migration scope

- Button #1: rest radius now resolves to `20px`; pressed state still resolves
  to `8px`.
- Button group #6 baseline: connected outer pill edges and selected pill
  sources now use finite local radius while selected+pressed inner corners
  still shrink to the intended M3 pressed values.
- Split button remains deferred to its own future component cycle.
- Static `corner-full` consumers remain unchanged by design.

### Phase 3 visual QA

Playwright + user visual QA confirmed:

- Button 5 variants: `20px -> 8px` active morph, no `9999px` interpolation
  flicker.
- Button group selected segment: `20px -> 4px` active morph.
- Button group first/last outer pill corners remain `20px` while inner pressed
  corners shrink.
- Icon button, FAB, Text field, and Search bar smoke checks show no unintended
  shape regression.

### Closed

- BACKLOG #31 — Pill radius interpolation / morphing-safe corner-full token.

### Validation

- Validator preserved: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- Edited files: `tokens.css` and `components.css` only.
- Untouched: `style-guide.html`, `blocks.css`, `theme.json`,
  `search-expansion/`, lab module artifacts, and runtime JS.

## v3.5.8 — Wave 1 — Search Bar #17 (2026-05-17)

Sixth Wave 1 closure and second Inputs family entry under the v3.5.0
public-surface framework. Search bar closes as the first dual-category
component that requires a four-doc audit shape because it owns an extracted
JavaScript runtime surface.

```txt
Text field validated:
  native/CSS interaction -> 3-doc audit trio.

Search bar validates:
  extracted JS runtime -> 4-doc audit shape with RUNTIME-AUDIT.
```

### Added — Search bar phase docs

- `docs/v3.5.8/SEARCH-BAR-PHASE-0-PLAN.md`
- `docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md`
- `docs/v3.5.8/SEARCH-BAR-PHASE-1-PLAN.md`
- `docs/v3.5.8/SEARCH-BAR-PHASE-2-PLAN.md`

### Added — Search bar audit set

- `products/reference-implementations/axismundi-lab/modules/search-bar/docs/SEARCH-BAR-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/search-bar/docs/SEARCH-BAR-MEASUREMENT-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/search-bar/docs/SEARCH-BAR-WP-MAPPING.md`
- `products/reference-implementations/axismundi-lab/modules/search-bar/docs/SEARCH-BAR-RUNTIME-AUDIT.md`

The audit records:

- Search bar is DISTINCT from Text field per v3.5.0 Phase 0B decision #7.
- `search-expansion/` remains preserved as historical v3.3.4 runtime evidence.
- The Search field host has ripple state NONE; composed trailing icon buttons
  consume Ripple v2 through their own consumer route.
- `popover/` remains CANDIDATE future alignment, not a v3.5.8 dependency.
- `core/search` is the primary WordPress mapping; autocomplete data, live
  results, federated search, and async status messaging remain plugin territory.

### Added — Search bar lab artifacts

- `products/reference-implementations/axismundi-lab/modules/search-bar/lab-search-bar.css`
- `products/reference-implementations/axismundi-lab/modules/search-bar/lab-search-bar.js`
- `products/reference-implementations/axismundi-lab/modules/search-bar/lab-search-bar-pattern.html`

### Phase 3 visual QA findings

User + Playwright QA surfaced three in-cycle corrections:

```txt
1. Mobile overflow at 390px viewport width.
2. Suggestion button activation leaked Material Symbols ligature text
   into the selected query value.
3. Browser-native search clear affordance duplicated the custom clear
   icon-button.
```

Resolution:

- Added a lab-scoped Search bar sizing guard:
  `box-sizing: border-box`, `max-inline-size: 100%`, and `min-inline-size: 0`.
- Added `data-search-value` to suggestion buttons so selected values are
  separated from decorative icon ligatures.
- Hid native `input[type="search"]` clear pseudo-elements for WebKit and
  legacy Microsoft engines because the composed trailing clear icon-button is
  the single visible clear affordance.

No new BACKLOG entry was opened; all three findings were fixed in-cycle.

### Changed — Matrix status

- `MODULE-STATUS-MATRIX.md` row #17 (Search bar): PARTIAL -> DONE (v3.5.8).
- Overall matrix distribution now: 10 DONE / 3 PARTIAL / 18 TODO / 3 RECORD
  component rows + 3 infrastructure provider rows = 37 canonical entries.
- Wave 1 now stands at 6/9 closures: Button, Icon button, Card, FAB family,
  Text field, and Search bar.

### Validation

- Validator preserved: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- Baseline preserved: `components.css`, `style-guide.html`, `tokens.css`,
  `blocks.css`, and `theme.json` untouched.
- `search-expansion/` preserved untouched as transitional runtime evidence.

## v3.5.7 — Wave 1 — Text Field #16 (2026-05-16)

Fifth Wave 1 component cycle and first Inputs family entry under the
v3.5.0 public-surface framework. This release closes the first
dual-category Component Full-Spec + Interaction component without creating a
standalone runtime audit doc.

```txt
Text field validates the rule:
  dual category does not automatically mean a fourth runtime audit.
  If interaction is native/CSS state behavior, the 3-doc trio remains correct
  and G11-G16 are covered inside SPEC/WP-MAPPING.
```

### Added — Text field phase docs

- `docs/v3.5.7/TEXT-FIELD-PHASE-0-PLAN.md`
- `docs/v3.5.7/TEXT-FIELD-PHASE-0-REPORT.md`
- `docs/v3.5.7/TEXT-FIELD-PHASE-1-PLAN.md`
- `docs/v3.5.7/TEXT-FIELD-PHASE-2-PLAN.md`

### Added — Text field audit trio

- `products/reference-implementations/axismundi-lab/modules/text-field/docs/TEXT-FIELD-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/text-field/docs/TEXT-FIELD-MEASUREMENT-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/text-field/docs/TEXT-FIELD-WP-MAPPING.md`

The audit records:

- Filled + outlined Text field coverage.
- Textarea as a Text field variant.
- Native validation in scope; custom validation and async status messaging remain plugin/integration territory.
- Static clear affordance and static counter specimens; no `lab-text-field.js`.
- Date/Time picker boundary: Text field owns the input shell, while calendar/clock picker behavior remains with Date picker #22 and Time picker #23.
- WordPress form mapping: theme-can for presentation, plugin-should for runtime validation, async messages, submission, and editor integration.

### Added — Text field lab artifacts

- `products/reference-implementations/axismundi-lab/modules/text-field/lab-text-field.css`
- `products/reference-implementations/axismundi-lab/modules/text-field/lab-text-field-pattern.html`

The pattern covers filled/outlined matrices, leading/trailing slots,
composed clear icon-button, prefix/suffix formatted input, native validation,
disabled and aria-disabled split, textarea, native input types, and
Playwright QA targets.

### Phase 3 visual QA findings

User visual QA surfaced three in-cycle corrections:

```txt
1. Filled variant matrix needed a leading-icon specimen.
2. Outlined Price affix specimen needed stable label behavior while editing.
3. Slots section Amount example was not aligned with M3 formatted-input guidance.
```

Resolution:

- Added a filled leading-icon specimen.
- Changed Price to a populated prefix-only currency specimen.
- Replaced Amount with a suffix-only Weight / kg unit specimen.
- Re-ran Playwright checks for label stability, prefix/suffix structure,
  absence of field-host ripple, and absence of `lab-text-field.js`.

### Framework validation

- First dual-category closure proves that native/CSS interaction does not need
  a separate runtime audit. Runtime audits remain reserved for extracted JS
  behavior layers such as queueing, positioning, timeout, focus-trap, or
  dismissal logic.
- G1-G10 and G11-G16 are all PASS at Phase 5.
- MEASUREMENT §10.0 records explicit WCAG SC applicability:
  SC 1.4.3, 1.4.11, 1.3.5, 3.3.1, 3.3.2, 3.3.3, 4.1.3, 2.5.8, and 2.5.5.
- BACKLOG #31 remains open as a separate baseline-correction candidate for
  morphing-safe pill radius interpolation; Text field did not mutate baseline.

### Changed — Matrix status

- `docs/v3.5.0/MODULE-STATUS-MATRIX.md` row #16 updated:
  Text field TODO → DONE (v3.5.7).
- Component status distribution updated to:
  DONE 9 / PARTIAL 4 / TODO 18 / RECORD 3.

### Validation

- Validator preserved: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- Baseline preserved: `components.css`, `style-guide.html`, `tokens.css`,
  `blocks.css`, and `theme.json` unchanged.
- No `CURRENT-STATE.md` or `NEXT-SESSION.md` churn.

## v3.5.6 — Ripple v2 Contract + data-ax-ripple Opt-In (2026-05-16)

Infrastructure amendment release under the v3.5.0 public-surface framework. This is not a Wave 1 component cycle; it closes the animated ripple provider contract that Button, Icon button, Card action surfaces, and FAB family had been routing toward.

```txt
v3.5.6 lands Ripple v2 as an Axismundi-native infrastructure contract.
It aligns with Material Web ripple concepts without importing <md-ripple>
or @material/web, and preserves components.css §0 as the static state-layer
foundation.
```

### Added — Ripple v2 phase docs

- `docs/v3.5.6/RIPPLE-V2-PHASE-0-PLAN.md`
- `docs/v3.5.6/RIPPLE-V2-PHASE-0-REPORT.md`
- `docs/v3.5.6/RIPPLE-V2-PHASE-1-PLAN.md`
- `docs/v3.5.6/RIPPLE-V2-PHASE-2-PLAN.md`

### Added — Ripple v2 audit contract

- `products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md`

The audit records the Interaction Runtime Infrastructure gate set, the Material Web alignment model, the two-layer hierarchy, and the stable public contract:

```txt
Declarative: [data-ax-ripple]
Variants:    bounded / unbounded
Tokens:      --md-ripple-* bridge + --ax-ripple-* implementation tokens
API:         window.axRipple.attach / detach / refresh
Migration:   HOST_SELECTOR allowlist remains transitional compatibility only
```

### Changed — Ripple runtime artifacts

- `products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css`
- `products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js`
- `products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple-pattern.html`

The v2 implementation adds `data-ax-ripple` discovery, bounded/unbounded provider classes, Material Web bridge tokens, pointer-only activation, disabled/forbidden-host guards, reduced-motion behavior, and `window.axRipple.attach/detach/refresh`.

### Phase 3 visual QA findings

The first visual pass surfaced an honest consumer-state correction: Nav bar and Nav rail were initially hypothesized as unbounded-preferred, but visual QA showed the ripple was too large, misaligned with icon geometry, and caused transient horizontal scroll in the nav rail demo.

Resolution:

```txt
Nav bar:  TARGET bounded
Nav rail: TARGET bounded
Tabs:     TARGET bounded on baseline .tabs__tab host
Pattern markup realigned to baseline wrappers:
  nav-bar__icon / nav-bar__label
  nav-rail__icon.has-state-layer inside nav-rail__item
  .tabs > .tabs__tab, not the invalid .tab demo class
```

This correction is recorded as a positive validation of the framework's "surface findings honestly" rule.

### Changed — Consumer-state matrix + closed consumer notes

- `docs/v3.5.0/MODULE-STATUS-MATRIX.md` row #36 updated with v3.5.6 amendment notice.
- Button, Icon button, Card, and FAB SPEC docs gained terse v3.5.6 Ripple v2 alignment notes.
- FAB family and Card action/interactive surfaces promoted from CANDIDATE to TARGET.
- Base Card remains NONE.

### Closed BACKLOG items

- #25 — Ripple v2 contract — Material Web alignment
- #27 — `data-ax-ripple` opt-in introduction

BACKLOG #26 remains closed at v3.5.4; its closed summary now notes the v3.5.6 bucket refinement.

### Validation

- Validator preserved: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- Baseline preserved: `components.css`, `style-guide.html`, `tokens.css`, and `blocks.css` unchanged.
- No `CURRENT-STATE.md` or `NEXT-SESSION.md` churn.

## v3.5.5 — Wave 1 — FAB Family #3 + #4 (2026-05-16)

Fourth Wave 1 component cycle under the v3.5.0 public-surface framework. This release closes FAB #3 and Extended FAB #4 together as the first real family-merge module (`fab/`), while keeping behavior-heavy Extended FAB patterns deferred.

```txt
v3.5.5 closes FAB #3 + Extended FAB #4 as one static Component Full-Spec family.
It does not implement Ripple v2, data-ax-ripple, FAB menu, Toolbar integration,
or Extended FAB collapse/expand behavior.
```

### Added — FAB family phase docs

- `docs/v3.5.5/FAB-PHASE-0-PLAN.md`
- `docs/v3.5.5/FAB-PHASE-0-REPORT.md`
- `docs/v3.5.5/FAB-PHASE-1-PLAN.md`
- `docs/v3.5.5/FAB-PHASE-2-PLAN.md`

### Added — FAB audit trio

- `products/reference-implementations/axismundi-lab/modules/fab/docs/FAB-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/fab/docs/FAB-MEASUREMENT-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/fab/docs/FAB-WP-MAPPING.md`

The trio records the family-merge decision, native button semantics, Pattern A disabled split, WordPress mapping limits, and the dependency profile:

```txt
state-layer foundation = CURRENT
icon-system/           = CURRENT unconditional
ripple/                = CANDIDATE
elevation tokens       = baseline token graph dependency
```

### Added — Lab artifacts

- `products/reference-implementations/axismundi-lab/modules/fab/lab-fab.css`
- `products/reference-implementations/axismundi-lab/modules/fab/lab-fab-pattern.html`

The pattern page covers FAB sizes, surface variants, static Extended FAB, native disabled, aria-disabled plugin-managed examples, state-layer opt-out, code snippets, and cross-references. No `lab-fab.js` was created.

### Fixed — Lab visual QA finding

User visual QA found that FAB size variants changed container dimensions but not Material Symbols glyph size. `lab-fab.css` now adds a lab-scoped glyph-size bridge for FAB Material Symbols:

```txt
.lab-fab-demo .ax-fab > .material-symbols-rounded
.lab-fab-demo .ax-fab-extended > .material-symbols-rounded
```

This keeps the fix out of baseline CSS while making the lab pattern visually truthful.

### Changed — Matrix and backlog

- `docs/v3.5.0/MODULE-STATUS-MATRIX.md` rows #3 and #4 now show FAB and Extended FAB as **DONE** (v3.5.5).
- `BACKLOG.md` adds **#30 Extended FAB behavior patterns**, deferring collapse/expand, auto-hide, FAB-to-menu transitions, and Toolbar choreography to a future behavior-pattern release.

### Validation

- Phase 3 Visual QA: PASS (user-verified).
- Baseline/public files preserved: `components.css`, `style-guide.html`, `tokens.css`, and `blocks.css` unchanged.
- Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS maintained.

## v3.5.4 — Matrix Consumer-State Amendment (2026-05-16)

Small foundation-cleanup release after the first three Wave 1 cycles. This release closes the matrix ambiguity surfaced by Button v3.5.1 and exercised through Icon button v3.5.2 + Card v3.5.3.

```txt
v3.5.4 closes BACKLOG #24 and #26.
It does not implement Ripple v2, data-ax-ripple, public SVG cleanup,
or Card behavior patterns.
```

### Changed — Matrix amendment

- **`docs/v3.5.0/MODULE-STATUS-MATRIX.md`** now has an explicit v3.5.4 amendment notice, avoiding silent rewrite of the original v3.5.0 Phase 1A deliverable.
- Added canonical consumer-state vocabulary:
  - `CURRENT`
  - `TARGET`
  - `CANDIDATE`
  - `NONE`
  - `CURRENT conditional`
  - `CURRENT conditional via composition`
- Updated Wave 1 closure statuses:
  - Button #1 → DONE (v3.5.1)
  - Icon button #2 → DONE (v3.5.2)
  - Card #9 → DONE (v3.5.3)

### Changed — Ripple row #36

`ripple/` row #36 no longer uses one flat inferred consumer list. It now records state-aware buckets:

```txt
CURRENT:
  none

TARGET:
  Button #1
  Icon button #2
  Chip #24
  Menu #15
  Nav bar #12
  Nav rail #13
  Tabs #14

CANDIDATE:
  FAB #3 + Extended FAB #4
  FAB menu #5
  Button group #6
  Split button #7
  Toolbar #8
  Card #9 action/interactive surface
  App bar #11 action slots
  List #33 item hover/action surface

NONE:
  Card #9 base visual card
  non-interactive components and baseline-only records unless separately promoted
```

`TARGET` explicitly means designed target / allowlist-bound, not baseline-wired. Current baseline-wired animated ripple remains `none`.

### Changed — Audit doc alignment notes

Short v3.5.4 matrix alignment notes added to:

- `products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/card/docs/CARD-SPEC-AUDIT.md`

Chip v3.4.9 intentionally remains unedited because it predates the consumer-state vocabulary. Its ripple TARGET classification is now recorded in the canonical matrix.

### BACKLOG routing

- **Closed #24** — Matrix consumer-state column.
- **Closed #26** — Matrix row #36 allowlist correction.
- Kept open:
  - #25 Ripple v2 contract
  - #27 data-ax-ripple opt-in
  - #28 Icon button public SVG wording cleanup
  - #29 Card behavior patterns

### Validator state

```txt
Last verified: 2026-05-16
Result:        1.000 / 1.000 / 1.000 / 1.000 PASS
```

---

## v3.5.3 — Wave 1 — Card #9 (2026-05-16)

The **third Wave 1 component module** authored under the v3.5.0 framework. Card enters as a Component Full-Spec Module with a different ontology shape than Button and Icon button: it is a container primitive first, while whole-card action/navigation is handled through native `<button>` / `<a>` semantics.

```txt
v3.5.3 executes Wave 1 item #9 (Card) end-to-end.
All SPEC verdict criteria PASS; G1-G10 promotion gates cleared;
baseline/public files preserved; MODULE-STATUS-MATRIX row #9 advances
TODO → DONE.
```

### Added — Phase 0 + Phase 1 + Phase 2 planning

- **`docs/v3.5.3/CARD-PHASE-0-PLAN.md`** — Plan-first entry for Card #9 inventory, including Card-specific risks around native semantics, `blocks.css` bridge status, disabled Pattern B, and ripple consumer-state.
- **`docs/v3.5.3/CARD-PHASE-0-REPORT.md`** — Phase 0 inventory and ontology framing. Key findings: `blocks.css` already contains a CURRENT partial `core/group` bridge; base Card ripple is NONE; interactive/action Card ripple is CANDIDATE; icon-system is composition-only conditional through `.card__actions`.
- **`docs/v3.5.3/CARD-PHASE-1-PLAN.md`** — Plan v1.0 with eight lock decisions: runtime audit N/A, width-flow model, disabled three-way split, native semantics decision tree, WP bridge audit-as-current, slot scope, icon-system composition, and ripple CANDIDATE.
- **`docs/v3.5.3/CARD-PHASE-2-PLAN.md`** — Plan v1.0 following Button/Icon button discipline: exactly two deliverable artifacts, unscoped `.card` overrides forbidden, `.lab-card-demo` scope marker, `.card__body` invention forbidden, user-facing static-catalog captions, and one current bridge specimen.

### Added — Phase 1 audit body (3-doc pattern)

Three audit documents at `products/reference-implementations/axismundi-lab/modules/card/docs/`:

1. **`CARD-SPEC-AUDIT.md`** — Card baseline inventory, variant/slot coverage, native semantics decision tree, disabled Pattern B split, dependency profile, WordPress bridge summary, G1-G10 gate mapping, and Phase 5 ALL-PASS verdict.
2. **`CARD-MEASUREMENT-AUDIT.md`** — Container padding/radius, elevation/outline, media/title/subtitle/actions slots, disabled Pattern B opacity, `blocks.css` bridge measurement, and WCAG static-vs-interactive applicability nuance.
3. **`CARD-WP-MAPPING.md`** — `core/group` bridge inventory, Card semantics decision tree, theme-can/plugin-should boundary, slot/action composition mapping, disabled mapping, ripple/state-layer mapping, ActivityPub/social CMS note, and 10 Card-specific anti-patterns.

### Added — Phase 2 deliverable artifacts

- **`products/reference-implementations/axismundi-lab/modules/card/lab-card.css`** (178 lines) — lab-internal demo scaffolding only. No unscoped `.card` overrides, no `.card__body`, no new M3 system tokens, no JS dependency, no ripple wiring.
- **`products/reference-implementations/axismundi-lab/modules/card/lab-card-pattern.html`** (372 lines) — 11-section pattern page: variants, media slot, title/subtitle/un-classed body content, actions slot composition, interactive action/navigation cards, disabled Pattern B three-way split, state-layer opt-out, current WordPress core/group bridge specimen, snippets, and cross-references.

### Card-specific ontology decisions

```txt
Static Card:
  article / section / div depending content context

Action Card:
  button type="button"

Navigation Card:
  anchor with href

Forbidden:
  article/div role="button" tabindex="0"
```

Dependency profile:

```txt
components.css §5 Card baseline            CURRENT
components.css §0 state-layer foundation   CURRENT for interactive card
blocks.css core/group card bridge          CURRENT partial
ripple/                                    NONE for base Card
                                           CANDIDATE for action/interactive Card
icon-system/                               CURRENT conditional via composition
```

### M3 guideline cross-check

Material Design 3 Card guidelines align with the v3.5.3 scope:

```txt
✓ elevated / filled / outlined card types
✓ media + text + actions anatomy
✓ Button / Icon button actions inside cards
✓ primary action area as whole-card action/navigation
✓ behavior-heavy patterns split out of primitive release
```

Deferred behavior-heavy patterns are routed to BACKLOG #29: expanding cards, swipe, pickup/reorder, and scrolling behavior.

### Phase 3 — Static Visual QA Gate

**PASS (2026-05-16).** User-verified `lab-card-pattern.html` visually after Phase 2. No blocking visual issues reported.

### Phase 5 — Mechanical close

SPEC verdict criteria all PASS:

```txt
✓ #1 Card variant coverage              PASS
✓ #2 Native semantics contract          PASS
✓ #3 Slot coverage                      PASS
✓ #4 Dependency declarations            PASS
✓ #5 Disabled semantics                 PASS
✓ #6 Phase 2 artifact completeness      PASS
✓ #7 Static Visual QA                   PASS
```

All applicable G1-G10 gates cleared. G11-G26 correctly N/A because Card is a Component Full-Spec consumer, not an Interaction Runtime / Record / Plugin-territory / Infrastructure provider.

### BACKLOG routing

- **BACKLOG #29 added** — Card behavior patterns: expanding, swipe, pickup/reorder, and scrolling. These are M3 Card behavior guidance items but deliberately deferred from v3.5.3's static primitive/component release.

### Baseline integrity

```txt
components.css §5 Card                         UNCHANGED
components.css §0 State-layer foundation       UNCHANGED
blocks.css §8 core/group card bridge           UNCHANGED
style-guide.html #components-card              UNCHANGED
```

### Validator state

```txt
Last verified: 2026-05-16 (Phase 5 close, Windows side)
Result:        1.000 / 1.000 / 1.000 / 1.000 PASS
```

---

## v3.5.2 — Wave 1 — Icon button #2 (2026-05-16)

The **second Wave 1 component module** authored under the v3.5.0 framework. Icon button enters as a Component Full-Spec Module and closes the main ontology gap left by v3.4.3's runtime prototype: `icon-system/` is no longer a conditional icon-slot dependency, but a **CURRENT unconditional** dependency because the icon is the component body.

```
v3.5.2 executes Wave 1 item #2 (Icon button) end-to-end.
All SPEC verdict criteria PASS; G1-G10 promotion gates cleared;
baseline/public files preserved; MODULE-STATUS-MATRIX row #2 advances
PARTIAL → DONE.
```

### Added — Phase 0 + Phase 1 planning

- **`docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md`** — Phase 0 inventory and ontology framing. Key finding: Button's `icon-system/` dependency was CURRENT-conditional; Icon button's dependency is CURRENT-unconditional. Also surfaced runtime-audit ownership risk and stale SVG public snippet risk.
- **`docs/v3.5.2/ICON-BUTTON-PHASE-1-PLAN.md`** — Plan v1.1 with five lock decisions and four reviewer findings reflected: runtime audit disposition, default-size-only scope, disabled split, BACKLOG #28 candidate routing, M3/WP mapping separation, WCAG positive finding, accessible-name contract.
- **`docs/v3.5.2/ICON-BUTTON-PHASE-2-PLAN.md`** — Plan v1.1 following Button #1 discipline: exactly two deliverable artifacts, unscoped `.ax-icon-button` overrides forbidden, `.lab-icon-button-demo` scope marker, `§5` / `§5a` disabled split, user-facing toggle-state caption in EN + KO.

### Added — Phase 1 audit body (3-doc pattern)

Three audit documents at `products/reference-implementations/axismundi-lab/modules/icon-button/docs/`:

1. **`ICON-BUTTON-SPEC-AUDIT.md`** — Icon button baseline inventory, `icon-system/` CURRENT-unconditional contract, runtime audit migration disposition, ripple TARGET deferral, Principle 1/2 native semantics, G1-G10 gate mapping, and Phase 5 ALL-PASS verdict.
2. **`ICON-BUTTON-MEASUREMENT-AUDIT.md`** — 40px visible container + 48px touch target, Material Symbols glyph sizing via `icons.css §1 + §5`, direct `.material-symbols-rounded` selector resolution, and WCAG target-size analysis.
3. **`ICON-BUTTON-WP-MAPPING.md`** — Core/block context inventory, accessible-name contract, 10 icon-button anti-patterns, ActivityPub/social CMS note, runtime audit migration note, and theme/plugin boundary.

### Positive accessibility finding

Icon button improves on Button #1 for target-size conformance:

```txt
Button #1:
  SC 2.5.8 AA  PASS
  SC 2.5.5 AAA not met by 40px button height

Icon button #2:
  SC 2.5.8 AA  PASS (48 >= 24)
  SC 2.5.5 AAA PASS (48 >= 44)
```

Reason: `.ax-icon-button` has a 40px visible container and a 48px interactive target via `--comp-touch-target`.

### Added — Phase 2 deliverable artifacts

- **`products/reference-implementations/axismundi-lab/modules/icon-button/lab-icon-button.css`** (126 lines) — lab-internal demo scaffolding only. No unscoped `.ax-icon-button` overrides, no new M3 system tokens, no JS dependency, no ripple wiring.
- **`products/reference-implementations/axismundi-lab/modules/icon-button/lab-icon-button-pattern.html`** (294 lines) — 8-section pattern page: variants, selected/unselected states, accessible-name contract, native disabled, aria-disabled plugin-managed, state-layer opt-out, code snippets, cross-references. Verified 21 native `<button>` specimens, 0 missing `type=`, 0 missing accessible names.

### Phase 2 pre-entry decision — Ripple strategy inherited

**Option (b) inherited from Button #1**: no current ripple wiring, no `lab-icon-button.js`, no `data-ax-ripple`. Icon button remains a valid TARGET ripple consumer, but animated ripple lands in the future Ripple v2 release (BACKLOG #25 / #27) together with other consumers.

### Phase 3 — Static Visual QA Gate

**PASS (2026-05-16).** User-verified `lab-icon-button-pattern.html` visually after Phase 2. No blocking visual issues reported.

### Phase 5 — Mechanical close

SPEC verdict criteria all PASS:

```txt
✓ #1 M3 icon button coverage              PASS
✓ #2 Token-driven implementation          PASS
✓ #3 Pattern HTML completeness            PASS
✓ #4 Phase 2 artifact completeness        PASS
✓ #5 Dependency declarations              PASS
✓ #6 Static Visual QA                     PASS
```

All applicable G1-G10 gates cleared. G11-G26 correctly N/A because Icon button is a Component Full-Spec consumer, not an Interaction Runtime / Record / Plugin-territory / Infrastructure provider.

### BACKLOG routing

- **BACKLOG #28 added** — Icon button public specimen SVG wording cleanup. `style-guide.html #components-icon-button` renders Material Symbols spans but still has stale SVG-era snippet/helper copy. This is routed to a later v3.5.x public-surface cleanup release, not fixed in v3.5.2.

### Baseline integrity

```
components.css §0 State-layer foundation        UNCHANGED
components.css §3 Icon button                   UNCHANGED
icons.css §1 + §5 Material Symbols integration  UNCHANGED
style-guide.html #components-icon-button        UNCHANGED
ICON-BUTTON-RUNTIME-AUDIT.md                    UNMOVED
```

### Validator state

```
Last verified: 2026-05-16 (Phase 5 close, Windows side)
Result:        1.000 / 1.000 / 1.000 / 1.000 PASS
```

---

## v3.5.1 — Wave 1 — Button #1 (2026-05-16)

The **first Wave 1 component module** authored under the v3.5.0 framework. Component Full-Spec Module for Button — baseline `components.css §2` (L122–L234) and `§0 State-layer foundation` (L22–L79) remain UNCHANGED at v3.5.1. The release delivers the lab module surface (CSS + pattern HTML), the 3-doc audit pattern (SPEC + MEASUREMENT + WP-MAPPING), and validates the v3.5.0 framework on its first real use case.

```
v3.5.1 closes the v3.5.0 Phase 0 framework dry-run by executing Wave 1
item #1 (Button) end-to-end. All 5 SPEC §11 verdict criteria PASS;
all applicable G1–G10 promotion gates cleared; baseline preserved 100%.
MODULE-STATUS-MATRIX row #1 (Button) advances TODO → DONE.
```

### Added — Phase 0 report (canonical inventory)

- **`docs/v3.5.1/BUTTON-PHASE-0-REPORT.md`** (876 lines, 38 KB, 15 sections) — Inventory + dependency scan + risk identification. Critical finding: Material Web ripple spec alignment audit (§3.5–§3.8) — surfaced the consumer-state ontology refinement (4 risks, all routed to v3.5.x amendments).

### Added — Phase 0.5 root context pack (Hybrid 5-file split)

Five repo-root operational files for multi-agent orchestration (Claude Code in cowork + local lanes, Codex local executor):

- **`CLAUDE.md`** — Claude Code operational rules
- **`AGENTS.md`** — Codex plan-first executor rules
- **`PROJECT-CONTEXT.md`** — long-horizon A–F architecture digest
- **`CURRENT-STATE.md`** — volatile release/phase status board
- **`NEXT-SESSION.md`** — session handoff entry point (discipline: updated only at true session boundaries)

### Added — Phase 1 audit body (3-doc pattern)

Three audit documents at `products/reference-implementations/axismundi-lab/modules/button/docs/`:

1. **`BUTTON-SPEC-AUDIT.md`** (~670 lines, 38 KB, 14 sections + ALL-PASS verdict) — variant coverage matrix (5 variants × S size), dependency declarations with consumer-state (3 deps × CURRENT / TARGET / CURRENT-conditional × DISTINCT-but-COUPLED contract), canonical icon slot pattern recorded, 4 Phase 0 risks disposed, G1–G10 gate readiness mapped.
2. **`BUTTON-MEASUREMENT-AUDIT.md`** (~355 lines, 24 KB, 10 sections) — M3 §4 measurement table (S size), Axismundi value extraction with token chain analysis, 100% token coverage on tokenizable properties (23/23), WCAG SC 2.5.8 AA met / SC 2.5.5 AAA gap documented honestly (40 < 44).
3. **`BUTTON-WP-MAPPING.md`** (~466 lines, 27 KB, 13 sections) — `core/button` → `.ax-button.is-*` block style variation mapping (5 variants), 3 rendering paths (block style / pattern / custom block), 10 anti-patterns enumerated, theme-can / plugin-should boundary (form submission), ActivityPub federation rendering surfaces.

### Added — Phase 2 plan + deliverable artifacts

Plan-only document then exactly 2 deliverable artifacts (per Phase 2 plan §1):

- **`docs/v3.5.1/BUTTON-PHASE-2-PLAN.md`** (v1.1, ~511 lines, 25 KB) — Phase 2 scope lock with 4 reviewer findings reflected: deliverable/bookkeeping separation, selector policy clarification (unscoped `.ax-button` overrides FORBIDDEN; `.lab-button-demo` scoped visualization ALLOWED for opacity/state-layer/token-swap), disabled specimen split (native vs aria-disabled plugin-managed), NEXT-SESSION.md discipline.
- **`products/reference-implementations/axismundi-lab/modules/button/lab-button.css`** (174 lines, 6.6 KB) — pattern variations + demo scaffolding, 0 unscoped `.ax-button` overrides, NO ripple wiring, module-private `--lab-button-*` variables only.
- **`products/reference-implementations/axismundi-lab/modules/button/lab-button-pattern.html`** (330 lines, 17 KB) — 8 sections: header, 5 label-only variants, 5 with-icon variants, has-state-layer opt-out demo (1 with + 1 without with bilingual caption), 5 native disabled, 1 aria-disabled plugin-managed (with explicit caption distinguishing it from native disabled), bare button, 6 collapsible code snippets, cross-references. Principle 1/2 verified: 19 `<button type=...>` instances, 0 `<div role="button">`, 0 `<span>` or `<a>` styled as button.

### Phase 2 pre-entry decision — Ripple wiring strategy

**SETTLED 2026-05-16: Option (b) — Defer animated ripple to Ripple v2 release (BACKLOG #25).** Consensus across User + GPT + Claude Opus. Rationale: Axismundi ontology-first flow aligns the consumer graph at the Ripple v2 contract release rather than wiring Button into the current Beer-CSS-derived ripple (Phase 0 §3.5.2). When Ripple v2 lands, Button + Icon button + FAB + Chip + Card etc. consumers get wired together in one coherent amendment release.

### Tooling cleanup (Codex executor, 2026-05-16)

Operational portability fixes (NOT a baseline change):

- **`tools/validators/validate_theme_pilot.py`** — `read_text` / `write_text` calls now specify `encoding="utf-8"`. Windows CP949 default fallback no longer breaks the validator on Korean Windows locales. No `python -X utf8` workaround needed.
- **`tools/generators/publish_styleguide.py`** — Hardcoded `ROOT = Path("/home/claude/axismundi")` replaced with `Path(__file__).resolve().parent.parent.parent` (script-location-relative). UTF-8 stdout/stderr reconfigure added for Windows console stability. Script now runs portably across Windows + Linux + cowork environments.

### Phase 3 — Static Visual QA Gate

**PASS (2026-05-16).** User-verified visual parity between `lab-button-pattern.html` and baseline `#components-button` rendering. 10-point gate per `BUTTON-PHASE-2-PLAN.md §5 G6` cleared with 0 actual issues.

### Phase 5 — Mechanical close

All 5 `BUTTON-SPEC-AUDIT.md §11` verdict criteria PASS:

```
✓ #1 M3 §4 spec coverage (S + 5 variants)        PASS
✓ #2 Token-driven implementation (100% tokens)   PASS
✓ #3 Pattern HTML completeness (8 sections)      PASS
✓ #4 Audit doc completeness (3-doc cross-refs)   PASS
✓ #5 Dependency declarations (consumer-state)    PASS
```

All applicable G1–G10 gates cleared (G11–G26 not applicable — Button is Component Full-Spec consumer, not Interaction Runtime / Record / Plugin-territory / Infrastructure provider).

### Architectural framework validation — v3.5.0 first real use

```
v3.5.0 framework held under first Wave 1 use case:
  ✓ 4-category fit               Button cleanly Component Full-Spec
  ✓ DISTINCT but COUPLED         3 deps × consumer-state ownership boundaries
  ✓ G1–G10 applicability         Universal gates applied; G11+ correctly N/A
  ✓ G13 spirit                   Phase 0 surfaced Risk 1 honestly; routed to amendment
  ✓ Phase 0 framework dry-run    4 risks + 4 amendment candidates surfaced;
                                  all routed to BACKLOG #24–#27 (v3.5.x amendments)
```

### Multi-agent orchestration validated

```
User                  Direction + final decisions + ontology authority
GPT                   Strategy review + reviewer findings (4 plan findings)
Claude Opus (cowork)  Phase 1 + Phase 2 audit/implementation execution
Claude Opus (this)    Phase 5 mechanical close
Codex (local)         Tooling cleanup (validator + publish portability fixes)
```

Process discipline locked:

- `CURRENT-STATE.md` — updated only at real phase-boundary changes
- `NEXT-SESSION.md` — updated only at session boundaries (new chat / EOD / major phase transitions)
- `docs/v3.5.x/<release>-PHASE-<n>-{PLAN,REPORT}.md` — canonical artifact per decision
- Edit-first / readback / abort on mismatch — fresh Write only for corruption recovery

### Baseline integrity (UNCHANGED throughout the v3.5.1 cycle)

```
components.css §0 State-layer foundation     L22–L79    UNCHANGED
components.css §2 Button                      L122–L234  UNCHANGED
style-guide.html #components-button anchor    L624–L693  UNCHANGED
tokens.css L817 / L818 / L834                            UNCHANGED
theme.json                                               UNCHANGED
```

### BACKLOG cross-references

```
v3.5.x amendments routed from Phase 0 findings:
  #24  Matrix consumer-state column                       (Phase 0 Risk 1)
  #25  Ripple v2 contract (Material Web alignment)        (Phase 0 §3.7)
  #26  Matrix row #36 allowlist correction (7 not 13)     (Phase 0 §3.5.4)
  #27  data-ax-ripple opt-in declarative attribute        (Phase 0 §3.8)
```

NO BACKLOG entries closed by v3.5.1 (this release routed Phase 0 findings TO BACKLOG; closes happen in subsequent v3.5.x amendment releases).

### Validator state

```
Last verified: 2026-05-16 (post-Phase 2 + post-tooling-cleanup, Windows side)
Result:        1.000 / 1.000 / 1.000 / 1.000 PASS
Publish:       publish_styleguide.py ran cleanly; styleguide/ mirror at 23 files
```

---

## v3.5.0 — Public Surface Reframe (2026-05-15)

A **policy / ontology / public-surface reframe release**, NOT an implementation release. Closes the v3.4.x interaction-module cycle and opens the Wave 1+ component module track by defining the rules under which all future module work operates.

```
v3.5.0 reframes Axismundi's public surface as a 37-entry ontology
matrix: 34 TOC components plus 3 infrastructure providers, governed
by promotion criteria, a public-surface charter, and the DISTINCT
but COUPLED dependency principle.
```

No new lab modules. No baseline changes. No rename execution. No pilot theme. The release is five policy documents that lock the operating rules so that the Wave 1+ releases can author component modules against a clear, externally-validated framework.

### Added — five policy documents under `docs/v3.5.0/`

1. **`33-COMPONENT-INVENTORY.md`** (Phase 0A + 0B, ~25 KB, 12 sections) — thin inventory of 34 baseline sections in TOC taxonomy order (Foundation / Actions / Containers / Navigation / Inputs / Selection / Feedback / Display); 4-category candidate assignment; 7 Phase 0B reconciliation decisions resolved (Avatar standalone, FAB+Extended FAB merged at module level, Date+Time merged, Menu/Popover "DISTINCT but COUPLED", Tabs dual-category, Slider no separate Interaction module, Search bar distinct from Text field); §11 module dependency graph introducing the third ontology axis; bilingual hard rule "CSS section count ≠ Component count".

2. **`MODULE-STATUS-MATRIX.md`** (Phase 1A, ~17 KB, 9 sections) — canonical 37-entry matrix: 34 TOC component rows + 3 infrastructure provider rows; 12 columns per row including Dep Type / Provider / Consumers axis. Status distribution: 3 DONE (Chip/Snackbar/Tooltip), 4 PARTIAL (Icon button/Search bar/Date+Time/Carousel), 24 TODO, 3 RECORD (Avatar/Divider/Badge). Bilingual hard rule: "Baseline-only Records are component rows with RECORD status, NOT additional matrix entries."

3. **`COMPONENT-COVERAGE-MAP.md`** (Phase 1A, ~19 KB, 6 sections) — three distribution maps: Map 1 (TOC × Category — Actions uniformly Full-Spec, Feedback mostly Runtime, Inputs deepest dual-category density), Map 2 (Wave × Status — Wave 1 = 9 entries with 3 PARTIAL leverage / Wave 2 = 14 entries largest / Wave 3 = 3 entries smallest), Map 3 (Infrastructure dependency graph — `ripple/` 13 consumers, `icon-system/` 10 consumers, `popover/` 5 consumers with DISTINCT but COUPLED contracts spelled out per provider).

4. **`PROMOTION-CRITERIA.md`** (Phase 1B, ~22 KB, 10 sections) — operating rules: status state machine (TODO → PARTIAL → DONE / RECORD final-state, regression triggers), category-specific completion criteria across 4 categories (Component Full-Spec / Interaction Runtime / Baseline-only Record / Plugin-territory Mapping), infrastructure qualification (multi-consumer, semantic neutrality, independent audit doc, stable contract), infrastructure boundary rules (DISTINCT but COUPLED enforcement language — `MAY` / `MUST NOT` for both infrastructure and consumer modules), dependency contract template, 26 validation gates G1–G26 (universal + category-specific + infrastructure), schedule items S1–S8 recorded but NOT executed.

5. **`PUBLIC-SURFACE-CHARTER.md`** (Phase 1B, ~21 KB, 9 sections) — architectural posture: 4-tier architecture (Public / Lab / Baseline / Plugin) with ASCII diagram and per-tier composition rules; surface-specific meanings locked (`style-guide.html` = baseline catalog NOT final app, `components.css §1-§34` = visual primitive source NOT runtime, `lab/modules/*` = validation surface NOT public contract, `bindings/` = plugin-territory mapping); Infrastructure dependency principle stated explicitly (EN + KO + WAI-ARIA APG Menu Button pattern alignment); naming sweep + theme policy + M3 Interpreter Plugin schedules recorded (NOT executed).

### Architectural framework — 3-axis ontology locked

```
Axis 1: TOC Group       Foundation / Actions / Containers / Navigation /
                        Inputs / Selection / Feedback / Display
Axis 2: Category        Component Full-Spec Module /
                        Interaction Runtime Module /
                        Baseline-only Module Record /  ← NEW v3.5.0
                        Plugin-territory Mapping       ← NEW v3.5.0
Axis 3: Dependency      Infrastructure (Provider) / Consumer / Independent
                        ← NEW v3.5.0 — surfaced by Phase 0B Menu/Popover refinement
```

The third axis is the central architectural finding of v3.5.0. Phase 0B Menu/Popover refinement surfaced the pattern that several lab modules are **infrastructure** (`popover/`, `ripple/`, `icon-system/`) consumed by multiple component modules. The "DISTINCT but COUPLED" framing formalizes the relationship: consumers depend on infrastructure runtime, infrastructure must not absorb consumer semantics.

### DISTINCT but COUPLED principle — formal text

```
Infrastructure modules may be public dependencies
without becoming public components.

A component module may depend on infrastructure runtime.
Infrastructure modules MUST NOT absorb consumer-specific semantics.
```

```
인프라 모듈은 public dependency가 될 수 있지만,
public component 그 자체는 아니다.

컴포넌트는 인프라 런타임에 의존할 수 있지만, 인프라 모듈이
소비자 컴포넌트의 의미론을 흡수해서는 안 된다.
```

External validation: WAI-ARIA APG Menu Button pattern + Material Design 3 spec both treat trigger button, menu surface, and anchored runtime as distinct concerns. Axismundi's DISTINCT but COUPLED framing aligns with both.

### 4-tier architecture — formally defined

```
Public Tier   ← Lab Tier (promotion when PROMOTION-CRITERIA met)
              ← Baseline Tier (always public, almost never mutates)
              ← Plugin Tier (out-of-tree, federation / data binding)

Composition rule:
  A module's existence in the lab tier does NOT imply public exposure.
  A module enters the public tier ONLY when it reaches DONE status
  per PROMOTION-CRITERIA category-specific criteria.
```

### 37-entry canonical matrix breakdown

```
34 TOC component rows
   3 DONE       Chip #24, Snackbar #28, Tooltip #29
   4 PARTIAL    Icon button #2 (icon-system/), Search bar #17
                (search-expansion/), Date+Time picker #22+#23
                (date-time/), Carousel #34 (carousel/)
  24 TODO       Wave 1 (6 net), Wave 2 (14), Wave 3 (3),
                and 1 family-merge anchor (Extended FAB)
   3 RECORD     Avatar #32, Divider #10, Badge #25

 3 infrastructure provider rows
  35  popover/      5 consumers (Menu, Split button, FAB menu,
                                 Date+Time picker, future Select)
  36  ripple/      13 consumers (Button family + Chip + List item, etc.)
  37  icon-system/ 10 consumers (Icon button, FAB family, Menu, etc.)

═══
37 canonical entries
```

### Wave grouping (Phase 0B confirmed)

```
Wave 1 (9 entries) — Core, high-frequency surfaces
  Button / Icon button / Card / Text field / Search bar /
  FAB family (+Extended) / List / Button group / Carousel

Wave 2 (14 entries) — Structural + form + transient
  App bar / Nav rail / Nav bar / Tabs / Menu / Dialog / Sheet /
  Checkbox / Radio / Switch / Toolbar / FAB menu / Split button /
  Date+Time family

Wave 3 (3 entries) — Lower-frequency / visualization
  Loading / Slider / Progress

Baseline-only Record sweep (3 records, no /modules/)
  Avatar / Divider / Badge
```

### Validation gates G1–G26 — locked

```
Universal      G1-G5    validate / baseline-untouched / publish / artifacts / CHANGELOG
Full-Spec      G6-G10   QA gate / Principle 1 / Principle 2 / WCAG SC / 3-doc audit
Runtime        G11-G16  hard rules / forbidden-ancestor / reduced motion / Phase 0 accuracy
Record         G17-G19  record doc path / variants+mapping / "why no module" justification
Plugin         G20-G21  mapping doc / theme-can/plugin-should boundary
Infrastructure G22-G26  multi-consumer / semantic neutrality / boundary rules /
                        independent audit doc / public dependency contract
```

### Phase 0B reconciliation decisions captured

The 7 medium-confidence reconciliation items surfaced during Phase 0A were resolved in Phase 0B and recorded in `33-COMPONENT-INVENTORY.md §7`:

```
1. Avatar #32              → STANDALONE under Display TOC group
2. FAB #3 + Extended FAB #4 → MERGE at module level (single FAB family module)
3. Date #22 + Time #23      → MERGE at module level (date-time/ already this shape)
4. Menu #15 + popover/      → DISTINCT but COUPLED — popover/ owns anchored
                              surface runtime, Menu owns menu semantics
5. Tabs #14                 → BOTH Full-Spec AND Interaction Runtime
6. Slider #21               → NO separate Interaction module (native input)
7. Search bar #17 + Text field #16 → DISTINCT components, distinct modules
```

### Scheduled (NOT executed in v3.5.0)

```
S1. .snackbar → .ax-snackbar rename sweep         (BACKLOG #18 — v3.5.x mini-release)
S2. data-theme="auto" 3-state model implementation (BACKLOG #22 — v3.5.x mini-release)
S3. Theme-only color customization policy enforcement (BACKLOG #20 — v3.5.x mini-release)
S4. Wave 1 module authoring (9 entries)            (v3.5.1+)
S5. Wave 2 module authoring (14 entries)           (v3.5.x — v3.6.x range)
S6. Wave 3 module authoring (3 entries)            (single release after Waves 1+2)
S7. Baseline-only Record sweep (3 records)         (v3.5.x mini-release, parallel)
S8. Ontology Theme Pilot                           (v3.6.0 — consumer of Wave 1 + form family)
```

Each scheduled item has its own dedicated path documented in the criteria + charter; no item is "left dangling".

### Latent infrastructure candidates (recorded, deferred)

```
focus-trap/   Dialog + Sheet both need focus-trap behavior.
              Decision deferred to Wave 2 — extract IF code overlap
              materializes during authoring.

backdrop/     Dialog + Sheet both render backdrop overlays.
              Same deferral path as focus-trap.

dismissible/  Snackbar / Dialog / Sheet / Tooltip — too semantic-coupled.
              Decision: NO extraction.
```

### Changed

- **`lab/modules/README.md`** — added reference to `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md` + brief Infrastructure dependency principle note; `MODULE-STATUS-MATRIX.md` cross-reference added.
- **`BACKLOG.md`** — schedule pointers added for #18 / #20 / #22 (NOT closed; close happens when actual sweep/implementation lands). No items closed in v3.5.0 (this is a policy release).

### Verification

1. `validate_theme_pilot.py` — **1.000 / 1.000 / 1.000 / 1.000 PASS** maintained throughout Phase 0 → 1A → 1B.
2. Cross-doc consistency — all 5 docs reference each other in the expected Phase 0 → 1A → 1B chain; PROMOTION-CRITERIA §9 + PUBLIC-SURFACE-CHARTER §8 "Cross-reference summary" sections explicitly map inputs/outputs.
3. G1–G26 all 26 distinct gates declared in PROMOTION-CRITERIA §7.
4. Phase 1B OUT items respected — no rename execution, no `data-theme` implementation, no `theme.json` modification, no new modules authored, no pilot theme work, no RC declaration.

### BACKLOG changes

- **#18** (.snackbar naming inconsistency) — open, schedule pointer added: v3.5.x mini-release ("naming sweep") per CHARTER §5.2.
- **#20** (Theme-only color customization policy) — open, schedule pointer added: v3.5.x mini-release ("theme policy") per CHARTER §6.2.
- **#22** (Explicit `data-theme="auto"` 3-state model) — open, schedule pointer added: v3.5.x mini-release ("theme policy") per CHARTER §6.1.

These three remain open — closure happens when the actual mini-release lands implementation. v3.5.0 is policy only.

### Forward path

- **v3.5.0 closes the v3.4.x interaction-module cycle and opens the Wave 1+ component module track.** All future module work operates against the 37-entry matrix, 4-tier charter, and G1–G26 validation gates.
- **v3.5.x mini-releases (next)** — Naming sweep (#18), Theme policy (#20 + #22), Baseline-only Record sweep (Avatar/Divider/Badge). Order TBD; can ship in parallel with Wave 1 work.
- **v3.5.1+ Wave 1 (NEXT, primary)** — first component module authored against v3.5.0 framework. Recommended: **Button #1** (highest-frequency surface, Wave 1 first item per coverage map).
- **v3.6.0** — Ontology Theme Pilot. Consumer of public-tier modules + infrastructure dependencies. Validates the 4-tier architecture against real WordPress.
- **v3.7.x → v1.0 RC** — 33-component audit coverage substantially complete; pilot iterating.

### One-line summary

```
v3.5.0 reframes Axismundi's public surface as a 37-entry ontology
matrix: 34 TOC components plus 3 infrastructure providers, governed
by promotion criteria, a public-surface charter, and the DISTINCT
but COUPLED dependency principle.
```

## v3.4.10 — Snackbar Runtime Module (2026-05-15)

The **second Interaction module outside the Beer CSS lineage** (date-time v3.4.7 was the first). Unlike previous Interaction modules — which extract runtime from a benchmark source — this release fills a runtime layer that the baseline explicitly carved out: the CSS section header at `components.css §14` L2041 contains the comment *"positioning + queue management live in prototype JS. This stylesheet defines visual chrome only."*

The `components.css §14 Snackbar` baseline (5 base selectors, full state-layer Pattern A on `.snackbar__action`, 24×24 container on `.snackbar__close`) remains **UNCHANGED** at v3.4.10. The module adds runtime positioning, queue, timeout, hover/focus pause, separated live announcement, reduced motion handling, coarse-pointer close hit-area expansion, and the `window.labSnackbar.{show, dismiss, dismissAll}` public API.

This release **closes the transient/feedback surface trio**:

```
Transient surfaces — complete:
  Popover-as-menu        ✓ v3.4.5
  Tooltip-as-description ✓ v3.4.6
  Snackbar-as-feedback   ✓ v3.4.10  ← this release
```

### Added

- **`lab/modules/snackbar/`** — new module directory with four artifacts:
  - **`lab-snackbar.css`** (~155 lines) — 5 sections (post Phase 0 correction): §1 close button hit-area expansion on coarse pointer (`::after` pseudo, scoped to `.lab-snackbar-host` to avoid colliding with baseline `.snackbar__action::before` state-layer); §2 positioning host wrapper with safe-area-inset (fixed bottom-center on mobile, bottom-left on desktop ≥768px); §3 `.is-open` / `.is-leaving` runtime states with fade + slight slide; §4 `prefers-reduced-motion: reduce` disables transitions; §5 `.lab-snackbar-live` visually-hidden utility.
  - **`lab-snackbar.js`** (~250 lines) — IIFE-wrapped runtime: LiveRegion singleton (creates one stable `<div class="lab-snackbar-live">` with `role="status"` + `aria-live="polite"` + `aria-atomic="true"`); TimeoutController class with start/pause/resume/cancel and remaining-time tracking; SnackbarQueue (FIFO, single-active, host wrapper at `<body>` scope); public API `window.labSnackbar.{show, dismiss, dismissAll}`; `show(message, options)` with `actionText`, `onAction`, `close`, `timeout`, `trigger`; default timeout resolver (explicit > close=true (0) > actionText (7000) > default (5000)); hover/focus pause via 4 events (pointerenter, pointerleave, focusin, focusout-with-relatedTarget-check); forbidden-ancestor trigger rejection (`.prose`, `.wp-block-post-content`, `.entry-content`, `[contenteditable]`).
  - **`lab-snackbar-pattern.html`** (~250 lines) — 5 demo sections: (1) Baseline static reference (4 Korean specimens), (2) Runtime show() variants with 4 trigger buttons (message-only / with action / action+close persistent / custom timeout), (3) Queue rapid-fire demo + dismissAll, (4) Forbidden-ancestor negative demo (button inside `.prose` must reject — output shows "✓ Rejected (returned false)"), (5) Reduced motion note.
  - **`docs/SNACKBAR-RUNTIME-AUDIT.md`** (~470 lines) — Bucket D Interaction module audit. 10 sections: critical framing (bilingual) + module category + provenance + trio closure, baseline/module split, inventory with bilingual Phase 0 correction notice, runtime policies (queue + timeout + public API + show/dismiss + action/close), 5 a11y hard rules with reasoning, reduced motion, forbidden ancestor, 5-criterion verdict (**PASS as a bounded Snackbar Runtime Module**, all 5 hard rules verified), out-of-scope inventory, one-line summary.

### Phase 0 inventory correction (recorded honestly)

The initial Phase 0 inventory under-counted baseline `§14 Snackbar` rules because the inventory regex missed indented selectors. The Static Visual QA Gate caught the mismatch during Phase 2 implementation — exactly the kind of discrepancy the gate is designed to surface. The audit doc `§3` records the correction with bilingual notice:

```
Phase 0 correction:
Initial inventory under-counted §14 Snackbar baseline rules because
indented selectors were missed by the inventory regex. Actual baseline
§14 includes styled .snackbar, .snackbar--two-line, .snackbar__label,
.snackbar__action, and .snackbar__close — five rule blocks, not two.
```

Impact on module scope:

```
Module CSS narrowed from 6 sections to 5:
  REMOVED  §1 action button override   (baseline already provides
                                        full state-layer Pattern A)
  KEPT     close hit-area expansion   (renumbered to §1, scoped to
                                        .lab-snackbar-host, uses ::after
                                        to avoid baseline ::before collision)

baseline §14 actual state (5 rules, 11 rule blocks including modifiers):
  .snackbar                            (container, full visual chrome)
  .snackbar--two-line                  (height variant)
  .snackbar__label                     (flex slot)
  .snackbar__action + ::before family  (state-layer Pattern A)
  .snackbar__close + child selectors   (24×24 + icon size)
```

The correction is not a failure — it is verification-system validation. Phase 0 inventory missed baseline rules; Phase 2 QA caught the mismatch before freeze; module scope was correctly narrowed to runtime-only expansion. This is recorded explicitly so future Component / Runtime module audits can adopt the more thorough inventory regex from the start.

### Changed

- **`lab/modules/README.md`** — snackbar row added to module inventory; v3.4.10 entry in version history; audit doc cross-reference added.
- **`BACKLOG.md`** — **#15 (Snackbar Runtime)** CLOSED by v3.4.10. **#18** (.snackbar naming inconsistency) explicitly carried forward to v3.5.0 Public Surface Reframe.

### Decisions captured in this release

- **Decision 1: Single live region + visible interactive surface separated** — a single stable `<div class="lab-snackbar-live" role="status" aria-live="polite" aria-atomic="true">` handles announcements; the visible snackbar (`.snackbar`) remains an interactive surface with focusable action/close buttons. The visible snackbar root is NEVER `aria-hidden` (Hard rule 1).
- **Decision 2: Single-at-a-time FIFO queue** — one snackbar visible at a time; additional messages enter FIFO queue. No stacking (stacking belongs to a toast framework, out of scope).
- **Decision 3: Configurable timeout, web-safe M3 defaults** — `show()` accepts explicit `timeout`; defaults are 5000ms message-only, 7000ms with action, 0 (persistent) with explicit close. 5000ms (vs Android 4000ms) is the conservative web default within M3's permitted range. Timeout MUST pause on pointer hover and keyboard focus (Hard rule 2, WCAG 2.2 SC 2.2.1 Timing Adjustable).
- **Decision 4 (corrected)**: Module CSS scope = runtime-only (positioning + states + reduced-motion + live-region + close hit-area expansion). Original Phase 0 plan to "fill `.snackbar__action` and `.snackbar__close` styling gap" was incorrect — baseline already provides full action/close visual chrome. Corrected during Phase 2 via QA gate.
- **Decision 5: Single audit doc** — Interaction module pattern (matches carousel/ripple/search-expansion/popover/tooltip/date-time), NOT 3-doc Component pattern.
- **5 Hard rules** locked in audit §5.2:
  - Hard rule 1: Visible snackbar root MUST NOT be `aria-hidden`.
  - Hard rule 2: Timeout MUST pause while pointer hover OR keyboard focus is inside.
  - Hard rule 3: Action and close MUST be real `<button>` elements.
  - Hard rule 4: `role="alert"` is NOT default.
  - Hard rule 5: Live region announces text-only feedback (no nested buttons).

### Verification

1. `python3 tools/generators/publish_styleguide.py` — publish surface 19 → 22 files (+`lab-snackbar.css`, plus a few publish-side artifacts; `lab-snackbar.js` and `lab-snackbar-pattern.html` and `docs/` intentionally NOT in publish — lab-internal).
2. `python3 tools/validators/validate_theme_pilot.py` — Overall **1.000 (PASS)**: A 1.000 / B 1.000 / C 1.000 / D 1.000.
3. Static Visual QA Gate (post Phase 0 correction): **PASS, 0 actual issues**. All 10 user-specified checks plus 6 post-correction-specific checks pass. Two earlier-version regex limitations (icon aria-hidden, distinct-vs-modifier rule count) manually confirmed safe.
4. Hard rules all verified: visible snackbar root never aria-hidden (only icon inside close button has aria-hidden, which is correct since the button itself carries aria-label="Close"); 4 timeout pause events + relatedTarget check; real `<button>` createElement; no `role="alert"` anywhere; live region uses `textContent` only.

### BACKLOG changes

- **#15 Snackbar Runtime** — **CLOSED** by v3.4.10. Resolution: `lab/modules/snackbar/` delivers runtime layer; baseline `components.css §14` UNCHANGED; module provides queue + timeout + hover/focus pause + separated live announcement + positioning + reduced motion + coarse-pointer hit-area expansion + public lab API.
- **#18 .snackbar naming inconsistency** — remains open, explicitly carried forward to v3.5.0 Public Surface Reframe (BACKLOG #11) for batched naming sweep.

### Manual browser verification (recommended but NOT blocking)

The static QA gate passed with 0 actual issues after Phase 0 correction. Browser-side checks remain valuable but are not freeze blockers since the runtime contract is statically verifiable:

- Show message-only snackbar — appears, dismisses at 5000ms.
- Show snackbar with action button — appears, action button is tab-focusable, click fires onAction then dismisses.
- Show snackbar with close button — appears persistently (timeout 0), close button dismisses.
- Rapid-fire show 4 snackbars — only one visible at a time, FIFO order.
- Hover/focus pause — timeout pauses while pointer hovers OR focus is on action/close button; resumes when both leave.
- OS Reduce-motion ON — snackbars appear/disappear instantly, queue + timeout still work.
- Trigger inside `.prose` — `show()` returns `false`, no snackbar appears.

Browser-only defects discovered after freeze should be handled as v3.4.10.1 micro-fixes.

### Forward path

- **v3.4.10 closes the transient/feedback surface trio.** Popover (v3.4.5) + tooltip (v3.4.6) + snackbar (v3.4.10) — the three transient surfaces of the interaction layer are now complete.
- **v3.5.0 (NEXT)** — **Public Surface Reframe** (BACKLOG #11). Scope: lab → public promotion criteria, `.snackbar` → `.ax-snackbar` rename sweep (BACKLOG #18), and explicit `data-theme="auto"` 3-state model (BACKLOG #22). Now unblocked since all transient surfaces and the first Component Full-Spec Module (chip v3.4.9) are in place.
- **v3.5.x+** — M3 Interpreter Plugin separation (BACKLOG #21).

### One-line summary

```
v3.4.10 closes Snackbar as a runtime-only module: baseline visual
chrome remains authoritative, while the lab module supplies queue,
timeout, live announcement, positioning, reduced motion, and
interaction safety.
```

## v3.4.9 — Chip Full Spec Module (2026-05-15)

The **first Component Full-Spec Module**. Unlike interaction modules (carousel v3.3.2, ripple v3.3.3, search-expansion v3.3.4, popover v3.4.5, tooltip v3.4.6, date-time v3.4.7), this release does not extract a runtime behavior from a benchmark source. Instead, it expands an existing baseline primitive into full Material 3 §14 spec coverage, measurement audit, and WordPress mapping audit.

The `components.css §11 Chip` baseline (L1626–L1743, 118 lines, 7 rule blocks) remains **UNCHANGED** at v3.4.9. The module adds three documented work items via `lab-chip.css`, demonstrates all four variants with principle-compliant native form controls in `lab-chip-pattern.html`, and establishes the three-document audit pattern (`-SPEC`, `-MEASUREMENT`, `-WP-MAPPING`) as the reference template for future Component modules.

This release also formalizes the **Module taxonomy** (Interaction vs Component) in `lab/modules/README.md` and adopts the **"Visible control must map to real runtime behavior"** principle as the first cross-module design rule. Both are seeded by the WordPress/M3 binding feedback memo (`bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md`, also new in this release).

### Added

- **`lab/modules/chip/`** — new module directory with five artifacts:
  - **`lab-chip.css`** (307 lines, 10.5 KB) — four-section CSS expanding the baseline primitive. §1 native form mapping for filter chips (`.chip__control:checked + .chip--filter` selector mirrors baseline selected state exactly); §2 input chip close button with hit-area expansion (24×24 container + ::before pseudo for ~44×44 on coarse pointer); §3 disabled state-layer suppression (3-selector explicit override); §4 pattern-page demo layout helpers (scoped to `.lab-chip-pattern`).
  - **`lab-chip-pattern.html`** (369 lines, 18.6 KB) — five demo sections: (1) Four-variant matrix with `<button onclick>` assist/suggestion + real action outputs, (2) Filter chip multi-select (4 native checkboxes), (3) Filter chip single-select (3 native radios with `<fieldset><legend>` group), (4) Input chip with avatar + real `<button class="chip__close" aria-label="Remove …">` close (3 instances), (5) Disabled states (4 variants covering `:disabled`, `aria-disabled="true"`, `.chip__control:disabled + .chip` patterns).
  - **`docs/CHIP-SPEC-AUDIT.md`** (332 lines, 18.9 KB) — Bucket E. 9 sections covering critical framing, baseline/module split, 4-variant matrix (assist HIGH / filter MEDIUM-HIGH / input MEDIUM / suggestion HIGH coverage), six explicit exceptions documented in baseline, five missing exceptions resolved or deferred, WCAG SC 2.5.8 AA + SC 2.5.5 AAA target-size framing for input chip close (Phase 2 decision: Option B + Option A-lite recorded), Principle 1/2 application per variant, and 5-criterion verdict (**PASS as the first Component Full-Spec Module**).
  - **`docs/CHIP-MEASUREMENT-AUDIT.md`** (188 lines, 11.0 KB) — closes BACKLOG #4. Records M3 §14 spec table, Axismundi current values extracted from baseline, deviation analysis (no deviations except open close-affordance target size now resolved), token alignment summary (19 of 20 measurement properties token-driven; 2 private literals `--_chip-h: 32px` and `--_chip-icon: 18px` with documented rationale in baseline), Phase 2 dimensions recorded.
  - **`docs/CHIP-WP-MAPPING.md`** (205 lines, 11.2 KB) — **first WordPress mapping audit**. Establishes Charter §4 application (theme can / plugin should split per rendering path), 7 core block contexts with action recommendations (`core/tag-cloud` primary block style target, `core/post-terms` secondary, others NO MAPPING or out of scope), 3 theme-side rendering paths (block style / pattern / custom block) with pros/cons, theme.json contract analysis (no chip-specific additions needed), 5 anti-pattern entries (button-as-chip, custom palette slug, etc.), 5 plugin-side surface index with BACKLOG cross-references.

### Changed

- **`lab/modules/README.md`** — two new sections inserted before "Module inventory":
  - **Module taxonomy** — formalizes Interaction vs Component module distinction. Includes summary table of authoring focus, typical artifacts, and when each category applies. Records that chip is the first Component Full-Spec Module.
  - **Design principles** (cross-module) — four numbered principles. Principle 1 is the core "Visible control must map to real runtime behavior" (sourced from `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md`). Principle 2 prefers native form semantics. Principle 3 forbids fake controls in demos. Principle 4 affirms publish-surface boundary.
  - Step 5 of "Adding a new module" updated — benchmark EXTRACTED marker step is now historical (retired at v3.4.8).
- **`BACKLOG.md`** — three new entries added (`#20` Theme-only color customization policy, `#21` M3 Interpreter Plugin separation, `#22` Explicit `data-theme="auto"` model) and one closed (`#4` Chip Measurement Audit). One new deferred (`#23` Elevated Chip Variants).

### New

- **`bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md`** (280 lines, 9 sections) — strategic memo, NOT a v3.4.9 blocker. Records color-picker concern + bridge strategy (3-stage path: Static / Preset / Semantic M3), 3-tier architecture (`theme.json` contract / `tokens.css` runtime / `--wp--preset--color--*` bridge), Interaction vs Component module taxonomy rationale, Strict mode vs Interpreter mode separation, Visible control principle source, v3.4.x impact analysis (none beyond Principle adoption at v3.4.9), and v3.5.0+ Interpreter Plugin scope preview. First line: *"This document is strategic feedback for the WordPress–Material 3 binding layer. It does NOT block v3.4.9 Chip Full Spec Module work."*

### Decisions captured in this release

- **Decision 1: Module taxonomy formalized** — Interaction modules validate behavior; Component modules expand baseline components into full-spec / measurement / variant / WordPress mapping surfaces. The two categories are not exclusive (a Component module may grow JS later); they record primary authoring focus.
- **Decision 2: Cross-module design principle "Visible control must map to real runtime behavior"** adopted and documented in `lab/modules/README.md`. Applies to every module's pattern HTML and audit demo sections going forward.
- **Decision 3: B-2 + C-1 scope** for v3.4.9 — three audit docs full set + JS deferred. The chip module ships without JS; filter chip state is real `:checked`, not JS-emulated.
- **Decision 4: 4 baseline variants only** (assist/filter/input/suggestion) — elevated variants routed to BACKLOG #23. Mixing elevated would dilute v3.4.9 as the first Component module template.
- **Decision 5: Input chip close affordance — Option B + Option A-lite** — real `<button class="chip__close">` with `aria-label`, 24×24 container meeting WCAG SC 2.5.8 AA on all pointer types, `::before` expansion meeting WCAG SC 2.5.5 AAA + Material touch convention on coarse pointer. Visible icon stays 18px. Option C (Backspace/Delete dismiss) deferred — requires JS.
- **Decision 6: Filter chip native form convention** — hidden `<input class="chip__control" type="checkbox|radio">` + sibling `<label class="chip chip--filter" for="…">`. Visible chip is the label; input carries state. Avoids `<input>`-as-chip styling (which requires extensive native input appearance reset).
- **Decision 7: Disabled state-layer suppression** — explicit selectors `.chip:disabled.has-state-layer::before`, `.chip[aria-disabled="true"].has-state-layer::before`, `.chip__control:disabled + .chip.has-state-layer::before` set `opacity: 0` to prevent fake hover/pressed feedback on non-interactive surfaces. Principle 1 application.

### Verification

1. `python3 tools/generators/publish_styleguide.py` — publish surface 18 → 19 files (+`lab-chip.css`). `lab-chip-pattern.html` and `docs/` intentionally NOT in publish (lab-internal).
2. `python3 tools/validators/validate_theme_pilot.py` — Overall **1.000 (PASS)**: A 1.000 / B 1.000 / C 1.000 / D 1.000.
3. Static Visual QA Gate — **PASS, 0 actual issues** (2 detected matches were false positives — `<button>` strings inside HTML comments). 13 real `<button>` elements all carry `onclick`, `disabled`, `data-theme-set`, or `aria-disabled`; 8 `chip__control` inputs paired 100% with 8 `chip--filter` labels via `for=`; 4 input chips have 4 `chip__close` buttons each with `aria-label`; 16 distinct chip CSS classes all defined in lab + baseline; no benchmark or out-of-tree references.
4. Boundary checks: `style-guide.html` UNCHANGED; `style-guide.html#components-chip` (L1782–L1855) UNCHANGED; `components.css §11 Chip` (L1626–L1743) UNCHANGED; baseline chip primitive carries no `lab-chip` references.

### BACKLOG changes

- **#4 Chip Measurement Audit** — **CLOSED** by v3.4.9 release. Resolution: `CHIP-MEASUREMENT-AUDIT.md` records M3 §14 spec table comparison, 19/20 properties token-driven, 2 private literals with documented rationale, and Phase 2 dimensions for input chip close affordance.
- **#20** Theme-only color customization policy — added, deferred to v3.4.x or v3.5.0.
- **#21** M3 Interpreter Plugin separation — added, deferred to v3.5.x+ milestone.
- **#22** Explicit `data-theme="auto"` 3-state model — added, deferred to v3.5.0.
- **#23** Elevated Chip Variants — added, deferred to v3.4.10+ after first Component module pattern stabilizes.

### Manual browser verification (recommended but NOT blocking)

The static QA gate passed with 0 actual issues. Browser-side checks remain valuable but are not freeze blockers since native HTML semantics provide higher static-QA confidence than benchmark-extracted JS:

- Checkbox filter chip click toggles selected visual state.
- Radio filter chip single-select works (arrow keys within radiogroup).
- Input chip close button receives focus ring; click removes chip.
- Disabled chips do not show state-layer feedback.
- Coarse-pointer hit-area on chip close feels acceptable (mobile/tablet).

Browser-only defects discovered after freeze should be handled as v3.4.9.1 micro-fixes.

### Forward path

- **v3.4.10 (NEXT)** — **Snackbar Runtime Module** (BACKLOG #15). Baseline visual primitive and four specimens already complete. What's deferred: queue management, auto-dismiss timeout, action handler, aria-live region wiring, reduced-motion entry/exit, mobile vs desktop placement. After v3.4.10 the "transient/feedback surface" trio closes: popover-as-menu (✓ v3.4.5) + tooltip-as-description (✓ v3.4.6) + snackbar-as-feedback (v3.4.10).
- **v3.5.0** — **Public Surface Reframe** (BACKLOG #11). Depends on tooltip + date-time + chip + snackbar all done. Paired with BACKLOG #22 explicit `data-theme="auto"` model.
- **v3.5.x+** — **M3 Interpreter Plugin** (BACKLOG #21). 3-stage bridge (Static / Preset / Semantic M3). Separate plugin, not theme.

## v3.4.8 — Benchmark Surface Deletion (2026-05-15)

The Beer-CSS-derived interaction module family closed at v3.4.6 tooltip; the GPT Codex-generated date/time interaction was extracted at v3.4.7. With no remaining extraction work targeting the benchmark surface, the three source files (`scripts/benchmark-interactions.js`, `stylesheets/benchmark-interactions.css`, `style-guide-benchmark.html`) are **deleted from the active tree**. Total: 306,772 bytes (300 KB).

Provenance is preserved in: the six extraction audit docs (`CAROUSEL-AUDIT.md`, `RIPPLE-AUDIT.md`, `SEARCH-EXPANSION-AUDIT.md`, `POPOVER-AUDIT.md`, `TOOLTIP-AUDIT.md`, `DATE-TIME-AUDIT.md`), `BEER-CSS-INTAKE.md`, the v3.3.2 – v3.4.7 zip freezes, CHANGELOG.md, ROADMAP.md, and git history. Each audit doc now carries a **v3.4.8 Deletion Notice** block at the top, explicitly marking line ranges within as historical references.

### Why complete deletion rather than `_archive/` move

Decision 2 of v3.4.8 Phase 0: 4 alternatives were considered (full deletion, `_archive/` move, partial keep-as-index, freeze-in-place). Full deletion chosen because:

1. **Provenance redundancy is already strong**: 6 audit docs + 1 intake contract + zip freezes + git history + CHANGELOG + ROADMAP — 11 independent provenance surfaces.
2. **`_archive/` introduces confusion**: active tree should be authoritative; `_archive/` invites accidental re-reference and creates a "is this still real?" ambiguity.
3. **The benchmark is no longer authoring surface**: every lab module is now the authoritative implementation; benchmark files are pure historical record, which is exactly what git is for.
4. **Active tree clarity is itself a feature**: with these files gone, the lab module structure stands clearly. Future readers don't have to ask "what's the difference between `benchmark-interactions.js` and `lab-*.js`?".

### Why no Charter EXTRACTED policy amendment was needed

Phase 0 pre-flight verified that `lab/docs/ARCHITECTURE-BOUNDARIES.md` does not contain a "retain forever" clause. The `BEER-CSS-INTAKE.md` "Add EXTRACTED markers to the benchmark copies" rule (rule 4) is now historical — the markers existed during v3.3.2 – v3.4.7 and are preserved in git/zip; the active tree no longer carries them. This is documented in the BEER-CSS-INTAKE.md v3.4.8 Deletion Notice.

### Why no module runtime dependency broke

Phase 0 pre-flight verified that **zero** lab module pattern HTML files, JS files, or CSS files load benchmark sources at runtime. All `benchmark-interactions` string occurrences in lab modules are comments / audit-trail references. Specifically:

- 6 lab module pattern HTMLs each load 2 scripts: `../../scripts/theme.js` and their own `lab-*.js`. No benchmark dependency.
- 0 `@import` statements reference benchmark CSS from anywhere.
- 0 `import` / `require` statements reference benchmark JS from anywhere.
- `style-guide.html` carries 0 benchmark references.
- `publish_styleguide.py` has 0 benchmark file references.

### Deleted

- `products/reference-implementations/axismundi-lab/scripts/benchmark-interactions.js` (64,962 B). 9 functions at deletion time: `enableRipple`, `enableAnchoredMenuDemos`, `enableSplitButtonMenus`, `enableSearchBar`, `enableBenchmarkModals`, `enableTooltips`, `enableSliderValueChips`, `enableMaterialYouSliders`, `enableDateBenchmarks`, `enableTimeBenchmarks`. All but `enableBenchmarkModals` had EXTRACTED markers pointing to lab modules at deletion time.
- `products/reference-implementations/axismundi-lab/stylesheets/benchmark-interactions.css` (29,451 B). Companion CSS for the benchmark JS. All major sections (ripple, search bar, menu, tooltip, date picker, time picker, slider) had been extracted to lab modules.
- `products/reference-implementations/axismundi-lab/style-guide-benchmark.html` (212,359 B, 3,589 lines). 33 component sections. The styleguide's old experimental surface — fully superseded by `style-guide.html` (baseline visual specimens) + 6 lab module pattern pages (live interaction).

### Changed (Deletion Notice amendments)

A `v3.4.8 Deletion Notice (added retrospectively)` blockquote was inserted near the top of each of 7 audit documents:

| Document | Notice variant | Size change |
|---|---|---:|
| `lab/docs/BEER-CSS-INTAKE.md` | Intake-specific (also retires intake rule 4 "Add EXTRACTED markers") | +1,257 B |
| `modules/carousel/docs/CAROUSEL-AUDIT.md` | Beer-CSS-derived standard notice | +605 B |
| `modules/ripple/docs/RIPPLE-AUDIT.md` | Beer-CSS-derived standard notice | +605 B |
| `modules/search-expansion/docs/SEARCH-EXPANSION-AUDIT.md` | Beer-CSS-derived standard notice | +605 B |
| `modules/popover/docs/POPOVER-AUDIT.md` | Beer-CSS-derived standard notice | +605 B |
| `modules/tooltip/docs/TOOLTIP-AUDIT.md` | Beer-CSS-derived standard notice | +605 B |
| `modules/date-time/docs/DATE-TIME-AUDIT.md` | GPT Codex-specific notice (acknowledges different lineage) | +685 B |

The notice format follows the user-recommended language:

```
Before:
Original retained in benchmark-interactions.js L...

After:
Original benchmark source was removed from the active tree at v3.4.8.
Provenance is preserved in this audit document, CHANGELOG/ROADMAP,
zip freezes, and git history.
```

Date/Time uses a variant explicitly acknowledging the GPT Codex provenance (NOT Beer CSS).

The body text of each audit doc is **NOT** mechanically edited. Line ranges, file paths, and quoted snippets remain as historical descriptions of where the source code lived during extraction. The Deletion Notice block at the top tells future readers to interpret all such references as historical.

### Side effects

- Publish surface mirror: previously had 1 stale `stylesheets/benchmark-interactions.css` (29,451 B mirrored despite no explicit publish_styleguide.py reference). v3.4.8 publish run cleared it automatically. Mirror dropped from 21 → 18 files.
- `validate_theme_pilot.py`: unaffected, still 1.000 PASS across A/B/C/D axes.

### Decisions captured in this release

- **Decision 1: Naming** — "Benchmark Surface Deletion" not "Retirement". The work is actual file deletion, not a softer change.
- **Decision 2: Full deletion vs `_archive/` move** — full deletion. Provenance redundancy already strong; `_archive/` invites confusion.
- **Decision 3: Amendment scope** — all 7 audit docs amended (BEER-CSS-INTAKE + 6 module audits). Mechanical insertion of a top-of-doc Deletion Notice rather than line-by-line rewrite, preserving each audit's body narrative as historical record.
- **Decision 4: Pause before v3.4.9** — verify clean state before entering Chip Full Spec Module phase. v3.4.8 ends here; v3.4.9 starts in a new phase after verification.

### Verification

1. `python3 tools/generators/publish_styleguide.py` — publish completed; surface dropped from 21 → 18 files (3 stale benchmark mirror files automatically cleared).
2. `python3 tools/validators/validate_theme_pilot.py` — Overall **1.000 (PASS)**: A 1.000 / B 1.000 / C 1.000 / D 1.000.
3. Active tree: 0 references to `scripts/benchmark-interactions.js`, `stylesheets/benchmark-interactions.css`, `style-guide-benchmark.html` in any non-historical-doc location. Remaining string mentions (CHANGELOG, ROADMAP, audit docs) are historical-narrative references protected by the Deletion Notice.

### Forward path

- **v3.4.9 (NEXT)** — **Chip Full Spec Module**. The first **component full-spec module** (not an interaction module). Existing baseline `.chip` primitive (7 classes, `components.css` §11) and `style-guide.html#components-chip` (74 lines) remain UNCHANGED. The new `lab/modules/chip/` will hold the full M3 spec, measurement audit (BACKLOG #4 closes here), all variants (assist/filter/input/suggestion + leading/trailing icon + selected/disabled/elevated states), and WordPress mapping notes (`CHIP-WP-MAPPING.md`). This is the **first component module rather than interaction module** — JS is optional, may be added later only if filter-chip selected-state needs runtime.
- v3.4.10 — Snackbar Runtime Module (BACKLOG #15). Baseline complete; runtime queue/timeout/aria-live deferred.
- v3.5.0 — Public Surface Reframe (BACKLOG #11).

## v3.4.7 — Date/Time Picker Interaction Extraction (2026-05-15)

GPT Codex-generated benchmark date/time picker interaction isolated as a lab module. **NOT a Beer-CSS-derived extraction** — the previous five lab modules (carousel v3.3.2, ripple v3.3.3, search-expansion v3.3.4, popover v3.4.5, tooltip v3.4.6) were extracted under `docs/BEER-CSS-INTAKE.md` contract; that family closed at v3.4.6. v3.4.7 is the first interaction-module extraction outside the Beer CSS lineage. The visual primitives (`components.css` §33 Date picker / §34 Time picker, 18 baseline classes total) were already Axismundi-native baseline components and remain UNCHANGED at v3.4.7.

Phase 0 inventory revealed the date/time interaction layer is **the largest extraction by an order of magnitude**: 684 lines JS (`enableDateBenchmarks` + `enableTimeBenchmarks`) + 428 lines CSS (`.ax-date-benchmark__*` / `.ax-time-benchmark__*` BEM trees) = 1,112 lines combined. By contrast, the largest previous extraction (popover v3.4.5) was ~288 lines and the smallest (tooltip v3.4.6) was 58.

Scope discipline therefore matters: v3.4.7 is **extraction + audit + provenance**, not a date-picker production-readiness pass. Carry-over policy applies: existing benchmark accessibility level preserved exactly, missing WAI-ARIA Date Picker grid navigation pattern recorded explicitly and routed to BACKLOG #19.

### Added

- **`lab/modules/date-time/`** — new module directory with four artifacts:
  - **`lab-date-time.js`** (805 lines, 30.0 KB) — IIFE wrapper around verbatim-extracted `enableDateBenchmarks` (363 lines) + `enableTimeBenchmarks` (321 lines). Public API: `window.labDateTime.init()`, plus read-only state inspectors `hasDatePickers` and `hasTimePickers`. Auto-init on `DOMContentLoaded`. Closure structure preserved exactly per audit doc §6 extraction strategy decision 1.
  - **`lab-date-time.css`** (452 lines, 10.0 KB) — verbatim extraction of `benchmark-interactions.css` L619-L1046 (428 lines, `.ax-date-benchmark__*` + `.ax-time-benchmark__*` BEM trees) plus header. Class names preserved verbatim per audit doc §6 decision 3 — renames deferred to a future cleanup pass.
  - **`lab-date-time-pattern.html`** (359 lines, 20.0 KB) — newly authored demo page (NOT a copy of `style-guide-benchmark.html` markup). Three sections: (1) Date picker interactive benchmark with single/range/input modes, (2) Time picker interactive benchmark with 12/24h modes + dial + input, (3) Forbidden-ancestor negative demo (`[data-date-benchmark]` inside `.prose` must NOT initialize). Theme switcher uses `data-theme-set` (canonical contract).
  - **`docs/DATE-TIME-AUDIT.md`** (~480 lines) — Bucket D. 8 sections covering critical framing (영문 + 한글), TL;DR, baseline/module/plugin split, full inventory with comparison to previous extractions, a11y inherited gaps with minimum-safety-fixes-vs-deferred split, what v3.4.7 does NOT fix (locked list), extraction plan, and five-criterion verdict (**PASS as an interaction extraction module, with critical inherited a11y gaps deferred**).

### Changed

- **`scripts/benchmark-interactions.js`** — two EXTRACTED markers:
  - L919 area: ~50-line block comment covering both `enableDateBenchmarks` and `enableTimeBenchmarks`. Documents provenance (GPT Codex, NOT Beer CSS, NOT covered by BEER-CSS-INTAKE), the 5 minimum safety fixes applied in lab module, and the critical inherited a11y gap routed to BACKLOG #19.
  - L1342 area (above `enableTimeBenchmarks`): 4-line cross-reference back to the combined block.
- **`stylesheets/benchmark-interactions.css`** — 1 EXTRACTED marker above L619 `/* Date picker benchmark phase */` covering both date and time CSS sections. Documents that this is NOT a Beer CSS extraction and that baseline `.ax-date-picker` / `.ax-time-picker` primitives in components.css remain UNCHANGED.

### Decisions captured in this release

- **Decision B (Phase 0)** — release name is "Date/Time Picker Interaction Extraction" (not "Module Extraction") to make the lineage explicit: visual primitive is baseline-already; only the GPT Codex interaction layer is moving.
- **Decision (Phase 0 #2)** — `style-guide-benchmark.html` is NOT touched in this release. That cleanup is its own v3.4.8 phase. Date-picker section L1651-L1839 (189 lines) and time-picker section L1840-L1989 (150 lines) preserved as historical reference.
- **Decision A (Phase 0 #3)** — single `lab/modules/date-time/` module folder, not split into `date-picker/` + `time-picker/`. Audit doc records the "two surfaces share field/input layer, diverge in selection model" reasoning.
- **Decision A (carry-over policy)** — preserve benchmark's existing accessibility level; do NOT add WAI-ARIA Date Picker grid navigation pattern; route the gap to BACKLOG #19. Audit doc §4 explicitly partitions "minimum safety fixes in v3.4.7" vs. "deferred to BACKLOG #19".

### Provenance — explicitly NOT Beer CSS

The release documents at every level (audit doc top, audit doc §1, audit doc §6, audit doc §8, lab-date-time.js header, benchmark-interactions.js EXTRACTED marker, benchmark-interactions.css EXTRACTED marker, CHANGELOG, ROADMAP) that this extraction is **NOT** a Beer CSS-derived extraction. The Beer CSS interaction-module family of 5 closed at v3.4.6 tooltip. Date/time is the first lab module extraction with a different provenance.

```
Date/Time Picker is NOT a Beer CSS-derived extraction.
The visual primitives are already Axismundi-native baseline components.
v3.4.7 extracts and audits the GPT Codex-generated benchmark interaction
layer into a bounded lab module.
```

### Minimum safety fixes (applied) vs deferred (BACKLOG #19)

**Applied in v3.4.7** (audit doc §4):

1. Module-scoped IIFE — no global helper leakage
2. Forbidden-ancestor bail-out — pickers inside `.prose`, `.wp-block-post-content`, `.entry-content`, `[contenteditable]` are skipped at init (Charter §5, broader selector list than popover/tooltip — added `.wp-block-post-content` and `.entry-content` for WordPress block editor surfaces)
3. Lab-only runtime — `style-guide.html` does not load `lab-date-time.js`
4. EXTRACTED markers in benchmark source — Charter §EXTRACTED policy
5. Public init API — `window.labDateTime.init()`

**Deferred to BACKLOG #19** (Date Picker Grid Navigation A11y):

- WAI-ARIA Date Picker grid structure (`role="grid"` / `role="gridcell"`, `aria-current="date"`, proper labelledby)
- Keyboard navigation (ArrowLeft/Right/Up/Down, Home/End, PageUp/PageDown, Enter/Space)
- Roving tabindex
- Month/year boundary announcement (`aria-live="polite"` region)
- Time picker WAI-ARIA refinements (possibly `role="radiogroup"` for hour/minute, spinbutton vs listbox decision)

### Verification

1. `python3 tools/generators/publish_styleguide.py` — publish surface 20 → 21 files; stylesheets 13 → 14 (+`lab-date-time.css`). `lab-date-time.js` and `lab-date-time-pattern.html` intentionally NOT in publish surface (lab-internal).
2. `python3 tools/validators/validate_theme_pilot.py` — Overall **1.000 (PASS)**: A 1.000 / B 1.000 / C 1.000 / D 1.000.
3. Static Visual QA Gate — **PASS, 0 actual issues**. 40 data-attribute selectors verified (39 static + 1 template-literal resolved at runtime); 49 structural elements 100% present; 14 helper functions all defined locally; theme.js contract satisfied. Browser-side manual verification is recommended but not blocking for v3.4.7; any browser-only defects discovered after freeze are handled as v3.4.7.1 micro-fixes.
4. Boundary checks: `style-guide.html` carries no reference to `lab-date-time.js`; `style-guide-benchmark.html` line count 3589 (UNCHANGED from Phase 0); `components.css` §33/§34 UNCHANGED; baseline `style-guide.html#components-date-picker` (L1550-L1645) and `#components-time-picker` (L1646-L1726) UNCHANGED.

### BACKLOG additions / changes

- **BACKLOG #19** added (open) — Date Picker Grid Navigation A11y. Records the 14 deferred a11y patterns (grid structure + keyboard nav + roving tabindex + announcements) with full scope, rationale for why this is NOT v3.4.7's scope, and sequencing dependency on at least one external pilot exposing real usage.
- **BACKLOG #17** updated wording — "Sequencing dependency" clause already covered this audit's relationship with the Pilot Block Theme Probe; no further edits needed.
- **BACKLOG #15** still deferred (Snackbar Runtime Module pushed from v3.4.7 to v3.4.9 to make room for Date/Time and Benchmark Surface Cleanup).
- **BACKLOG #18** still deferred (Snackbar class naming inconsistency, scheduled for v3.5.0 Public Surface Reframe).

### Forward path

- **v3.4.8 (NEXT)** — **Benchmark Surface Cleanup**. Retire `style-guide-benchmark.html` (3589 lines, 33 component sections) as the styleguide's authoritative source. Sections whose interaction layer is in a lab module (carousel, date-picker, time-picker, search-bar, menu, tooltip) route to module pattern pages; visual-only sections (card, chip, badge, dialog, sheet) merge into `style-guide.html` if not already there. No module work; pure surface restructuring.
- **v3.4.9** — **Snackbar Runtime Module** (BACKLOG #15). Baseline already complete; runtime queue/timeout/aria-live deferred.
- **v3.5.0** — Public Surface Reframe (BACKLOG #11) — depends on tooltip + date/time both done; the lab-flask popup UX is implementable as soon as the surface reframe is ready.

## v3.4.6 — Tooltip Module Extraction (2026-05-14)

Fifth and final Beer-CSS-derived lab module under `docs/BEER-CSS-INTAKE.md` contract. After v3.4.6 the Beer CSS interaction-module family is closed: carousel v3.3.2, ripple v3.3.3, search-expansion v3.3.4, popover v3.4.5, tooltip v3.4.6. Snackbar (BACKLOG #15) is intentionally Axismundi-native from scratch, not a Beer CSS extraction.

Phase 0 audit confirmed `compare/beer-css/` is already absent from the active tree (removed at an earlier point). The retirement audit (originally planned as Phase 0A) is therefore a no-op — Beer CSS source material no longer lives in the repository. Attribution remains preserved in `NOTICE.md` and `LICENSE-MATRIX.md`. The "Code audit pending before public release" language in those files is **intentionally not touched** at v3.4.6 — that is a separate Release Readiness / License Audit phase tied to v3.5.0, not a Beer CSS extraction concern.

### Added

- **`lab/modules/tooltip/`** — new module directory with four artifacts:
  - **`lab-tooltip.js`** (10,017 B, ~280 lines) — Axismundi-native reimplementation of `createTooltip`, `positionTooltip`, `enableTooltips`. Public API: `window.labTooltip.init()`, `show(trigger)`, `hide(trigger?)`, plus read-only state inspectors `isVisible` and `activeTriggerId`. Auto-init on `DOMContentLoaded`. Single tooltip element pattern (`#lab-tooltip-singleton`) with `role="tooltip"`.
  - **`lab-tooltip.css`** (3,526 B, 2 sections) — runtime-specific layer only. Does NOT redefine `.ax-tooltip` visual primitive (kept verbatim in `components.css` L3089-L3230, 142 lines). Adds only the singleton runtime selector hook and module pattern page layout helpers. Reduced-motion is handled at primitive level (token-gated), so no override block here — different from popover v3.4.5 which needed an explicit override.
  - **`lab-tooltip-pattern.html`** (14,832 B) — five demo sections: (1) plain hover on icon button, (2) plain hover with explicit `[data-tooltip]` opt-in + priority test, (3) keyboard focus path through Tab navigation, (4) forbidden-ancestor bail-out negative demo with triggers inside `.prose`, (5) rich tooltip visual specimen with `tabindex="-1"` + `aria-hidden="true"` (interactive wiring deferred). Theme switcher uses `data-theme-set` from authoring.
  - **`docs/TOOLTIP-AUDIT.md`** (~22 KB) — Bucket D. Records Tooltip vs Popover 12-row contract divergence table, Beer CSS intake (9 rules + Tier A/B partial-compliance note for rule 4), function inventory (3 functions, 58 lines original), extraction plan (9 JS reimplementation principles with tooltip-specific additions: aria-describedby lifecycle, no-focus-movement invariant, asymmetric show/hide pairs, narrowed selector per Decision B, `relatedTarget` checks), a11y risk register (5 risks with mitigations + risk 5 OUT-of-scope acknowledgment for rich variant), forbidden-ancestor bail-out policy, Decision B + Decision Y captures, five-criterion verdict (PASS as a lab module).

### Changed

- **`scripts/benchmark-interactions.js`** — ~50-line `/* EXTRACTED: v3.4.6 → modules/tooltip/lab-tooltip.js */` block comment inserted above L473 `createTooltip`. Lists all 3 extracted functions, enumerates the 5 issues fixed during refinement, and explicitly captures Decision B (selector narrowing) and Decision Y (touch/delay deferral). Originals retained verbatim per Charter EXTRACTED policy.
- **`docs/BEER-CSS-INTAKE.md`** — two table updates:
  - Item 4 "Tooltip" status: `"held — necessity re-evaluated in v3.3.6"` → `"v3.4.6 — Done"`.
  - Module audit table: `popover` row corrected from `"planned v3.4.1"` (stale) to `"Done — v3.4.5"`. `tooltip` row added with `"Done — v3.4.6"`. `(date/time/tooltip/modal)` held bucket trimmed to `(date/time/modal)`.
- **`modules/README.md`** — tooltip row added to module inventory table; `tooltip/docs/TOOLTIP-AUDIT.md` reference added to module-specific audits list.

### Decisions captured in this release

- **Decision B (Phase 0)** — Trigger selector narrowed to `[data-tooltip], .ax-icon-button[aria-label]`. The `.ax-button[aria-label]` half is removed because text buttons typically have visible labels and tooltip exposure would duplicate information. Authors who want a text button to show a tooltip use the universal `[data-tooltip]` opt-in instead.
- **Decision Y (Phase 0)** — Hover show delay and touch long-press refinement deferred to BACKLOG #16. v3.4.6 ships the minimal accessible hover/focus runtime only.
- **Phase 2 implementation decision** — Rich tooltip variant is shipped as a **visual specimen only**. Static `.is-rich.is-open` rendering in pattern HTML §5 with `tabindex="-1"` + `aria-hidden="true"` allows the M3 §34.3 visual primitive to be audited without bringing rich tooltip interactive wiring (Tab-into-action, Escape with focus restoration, hover-into-tooltip-body preservation) into v3.4.6 scope. Rolled into BACKLOG #16's deferred scope at audit-doc time.

### Scope discipline

- **Lab module only** — `lab-tooltip.js` is NOT loaded from `style-guide.html`. The baseline keeps `.ax-tooltip.is-open` as a static visual specimen; live interaction is verified only inside `lab-tooltip-pattern.html`. Same posture as popover v3.4.5 and ripple v3.3.3 — baseline promotion deferred as separate Charter §1 decision.
- **`components.css` `.ax-tooltip` primitive UNCHANGED** — 142 lines at L3089-L3230 are not modified by this release. `lab-tooltip.css` adds only runtime helpers and module pattern layout.
- **OUT scope (carried over to future releases)**: hover show delay (BACKLOG #16), touch long-press (BACKLOG #16), rich tooltip interactive wiring (BACKLOG #16), command palette, dynamic tooltip registry, plugin tooltip API, snackbar feedback module (BACKLOG #15), tooltip baseline promotion, NOTICE/LICENSE-MATRIX "Code audit pending" language (Public Surface / License Audit phase).

### Five issues fixed during refinement (vs. benchmark originals)

1. `aria-describedby` was 0 occurrences in benchmark (critical a11y gap) → now wired on show with defensive `existing-value` check; removed on hide with defensive ID-match check.
2. Missing forbidden-ancestor bail-out → explicit `.prose` / `[contenteditable]` check on every trigger event path (Charter §5).
3. Global always-on listeners (pointerover/out/focusin/out + scroll/resize) → **Tier A / Tier B split**. Tier A (pointer + focus enter/leave) stays always-on by tooltip nature (multi-trigger delegation); Tier B (scroll/resize/Escape) attaches only while visible. Documented as BEER-CSS-INTAKE rule-4 partial-compliance note in audit doc.
4. `pointerout` collision with rich tooltip self-hover → `event.relatedTarget` check skips hide when pointer moves from trigger INTO the tooltip body (protects future rich interactive even though v3.4.6 doesn't wire it).
5. Trigger selector narrowed per Decision B — `.ax-button[aria-label]` removed.

### BACKLOG resolution

- **BEER-CSS-INTAKE.md item 4 "Tooltip"** — closed (status: Done — v3.4.6).
- **#16 (Tooltip delay and touch long-press refinement)** — re-scoped to include rich tooltip interactive wiring. Three deferred items now grouped under one BACKLOG entry for the inevitable "Tooltip Interaction Polish" phase.

### Verification

1. `python3 tools/generators/publish_styleguide.py` — publish surface 19 → 20 files; stylesheets 12 → 13 (`lab-tooltip.css` added). `lab-tooltip.js` is intentionally NOT in publish surface (lab-internal).
2. `python3 tools/validators/validate_theme_pilot.py` — Overall 1.000 (PASS): A 1.000 / B 1.000 / C 1.000 / D 1.000.
3. `style-guide.html` carries no reference to `lab-tooltip.js` (live wiring boundary preserved).

### Forward path

- **v3.4.7 (NEXT)** — **Snackbar Feedback Module** (BACKLOG #15). Axismundi-native from scratch — `compare/beer-css/` is gone, so no extraction path. The "transient/feedback surface" trio closes after snackbar lands: popover-as-menu (v3.4.5) + tooltip-as-description (v3.4.6) + snackbar-as-feedback (v3.4.7).
- **v3.4.8** — Promotion Criteria / Public Surface Prep (`lab/docs/PROMOTION-CRITERIA.md` + publish-doc generated-artifact note + typography-axis link from Typography section).
- **v3.5.0** — Public Surface Reframe (BACKLOG #11) — depends on v3.4.5 popover (✓ done) and v3.4.6 tooltip (✓ done now); the lab-flask popup UX is implementable as soon as the surface reframe is ready.

## v3.4.5.1 — Theme Switcher Sync Selector Fix (2026-05-14)

Visual QA Gate follow-up to v3.4.5. One-line `theme.js` patch resolving the last symptom in the theme-switcher cohort-fix family: module pattern pages now sync the correct `aria-checked` / `is-selected` button on reload.

### Root cause

`theme.js` `syncSwitchers()` was scanning `document.querySelectorAll(".ax-theme-switcher")`, a class only used in `products/_archive/axismundi-prototype/`. The active codebase (`style-guide.html` + 5 module pattern HTMLs) uses `.sg-theme`. Result: `syncSwitchers(initial)` at script init found zero groups, so on reload the `aria-checked="true"` hardcoded on `<button data-theme-set="auto">` was never updated to match the stored mode. The palette itself toggled correctly (because `apply()` runs separately and just sets the `data-theme` attribute on `<html>`), but the visible selection indicator drifted from the actual mode.

`style-guide.html` masked the bug because `style-guide.js` L70 carries its own theme handler (`[data-theme-button]` listener with its own sync) that takes precedence. Module pattern pages don't load `style-guide.js`, so they had no fallback.

### Cohort-fix family

This is the third entry in the cohort-fix family started at v3.4.3.1:

| Release | Symptom | Fix |
|---|---|---|
| **v3.4.3.1** | Theme switcher buttons inert | `role="group"` → `role="radiogroup"` across 4 module patterns |
| **v3.4.5** | Buttons toggled visually but palette didn't change | `data-theme-button` → `data-theme-set` across 4 module patterns |
| **v3.4.5.1** | Palette changed but selection indicator drifted on reload | `.ax-theme-switcher` → `.sg-theme, .ax-theme-switcher` in `theme.js` syncSwitchers selector |

Each fix touched a different layer of the same handshake (role / attribute name / class name). With v3.4.5.1 the handshake is complete: button click → `theme.js` click handler matches `[data-theme-set]` → `apply(mode)` sets `<html>` data-theme → `syncSwitchers(mode)` matches `.sg-theme` group → `aria-checked` + `.is-selected` on the correct button.

### Changed

- **`scripts/theme.js`** — two edits:
  - **L506-517**: `syncSwitchers()` selector changed from `".ax-theme-switcher"` to `".sg-theme, .ax-theme-switcher"`. Defensive: both class names accepted. `.sg-theme` is canonical (active code); `.ax-theme-switcher` retained for archive/axismundi-prototype backward compatibility.
  - **L452-468**: doc comment "Markup contract" section updated to show `.sg-theme` as the canonical pattern with `.ax-theme-switcher` noted as legacy/archive-only.

### Scope discipline

- No HTML markup changes — the fix is entirely on the JS side. `style-guide.html` (still uses `data-theme-button` with `style-guide.js`'s own handler) is unchanged. 5 module pattern HTMLs (using `data-theme-set` + `.sg-theme` after v3.4.5) are unchanged.
- No CSS changes.
- No impact on `style-guide.html` theme switcher (still owned by `style-guide.js` per the explicit `hasProductionSwitcher / hasStyleGuideSwitcher` guard in theme.js L476-483).

### Verification

1. `python3 tools/generators/publish_styleguide.py` — publish surface still 19 files. `styleguide/scripts/theme.js` reflects the fix (publisher copies `scripts/theme.js` verbatim, no path rewriting needed for JS).
2. `python3 tools/validators/validate_theme_pilot.py` — Overall 1.000 (PASS): A schema 1.000 / B theme 1.000 / C css 1.000 / D runtime 1.000.
3. Manual verification path: open any module pattern page (e.g. `lab-popover-pattern.html`), click Light, reload — `Light` button should remain selected (aria-checked="true" + .is-selected). Previously `Auto` button would show selected even though the palette was Light.

### BACKLOG resolution

- **#12 (Theme switcher sync selector mismatch)** — added and immediately closed at v3.4.5.1. Listed in cohort-fix family alongside #8 (v3.4.3.1) and #9 (v3.4.5).
- **#10 (Lab ripple verification)** — verification path was already unblocked at v3.4.5; now also visually consistent (selected button matches actual palette) so a ripple visual-QA pass can proceed without theme-switcher confusion. Item #10 remains open until the actual verification pass is performed.

### v3.4.6 candidate choice unchanged

v3.4.5.1 is a Visual QA Gate patch only. The v3.4.6 candidate decision (Tooltip Module Extraction vs. Styleguide UX prep) remains deferred until after the rest of Visual QA Gate completes.

## v3.4.5 — Popover/Menu Module Extraction + Theme Switcher Cohort Fix (2026-05-14)

Fourth Beer-CSS-derived lab module under the `docs/BEER-CSS-INTAKE.md` contract (after carousel v3.3.2, ripple v3.3.3, search-expansion v3.3.4). Extracts six benchmark menu/popover functions into `lab/modules/popover/` with Axismundi-native reimplementation, fixes nine issues identified during Phase-1 audit, and ships **as a lab module only** — explicitly NOT promoted to baseline theme interaction layer. Bundled cohort-fix: `data-theme-button` → `data-theme-set` rename across the four existing module pattern HTMLs, closing BACKLOG #9.

### Added

- **`lab/modules/popover/`** — new module directory with four artifacts:
  - **`lab-popover.js`** (12,241 B, ~290 lines) — Axismundi-native reimplementation of `makeMenu`, `positionMenu`, `openBenchmarkMenu`, `closeBenchmarkMenu`, `enableAnchoredMenuDemos`, `enableSplitButtonMenus`. Public API: `window.labPopover.init(root)`, `window.labPopover.close()`, plus read-only state inspectors `isOpen` and `openMenuId`. Auto-init on `DOMContentLoaded`.
  - **`lab-popover.css`** (4,693 B, 6 sections) — runtime-specific layer only. Does NOT redefine `.ax-menu` visual primitive (kept in `components.css`). Adds: `position: fixed` + z-index for wired menus, `[data-popover-trigger][aria-expanded="true"]` hook, `.ax-menu__item:focus-visible` ring, reduced-motion override, module pattern page layout helpers.
  - **`lab-popover-pattern.html`** (12,707 B) — interactive demo page with three sections: anchored menu (single-button trigger), split-button menu (primary action + chevron trigger), forbidden-ancestor bail-out demo (trigger placed inside `.prose` that must not fire). Theme switcher uses `data-theme-set` from authoring (canonical contract).
  - **`docs/POPOVER-AUDIT.md`** (13,057 B, ~330 lines) — Bucket D. Records Beer CSS intake (9 rules), function inventory (6 functions, 138 lines original), extraction plan (file layout + CSS scope discipline + JS reimplementation principles), a11y risk register (5 risks with mitigations), forbidden-ancestor bail-out policy, reduced-motion strategy, Phase 4b cohort fix description, and five-criterion verdict (PASS as a lab module).

### Changed

- **`scripts/benchmark-interactions.js`** — 50-line `/* EXTRACTED: v3.4.5 → modules/popover/lab-popover.js */` block comment inserted above L96 `makeMenu`. Lists all 6 extracted functions, enumerates the 9 issues fixed during refinement, and documents the unconditional-listener → open-scoped-listener change. Originals retained verbatim per Charter EXTRACTED policy (no pruning).
- **`modules/carousel/lab-carousel-pattern.html`** — `data-theme-button` → `data-theme-set` (3 occurrences, light/dark/auto buttons). Cohort fix Phase 4b.
- **`modules/ripple/lab-ripple-pattern.html`** — same rename (3 occurrences). Cohort fix Phase 4b.
- **`modules/search-expansion/lab-search-expansion-pattern.html`** — same rename (3 occurrences). Cohort fix Phase 4b.
- **`modules/icon-system/icon-system-pattern.html`** — same rename (3 occurrences). Cohort fix Phase 4b.
- **`modules/README.md`** — popover row added to module inventory table; `popover/docs/POPOVER-AUDIT.md` reference added to module-specific audits list.

### Scope discipline

- **Lab module only** — `lab-popover.js` is NOT loaded from `style-guide.html`. The baseline keeps `.ax-menu.is-open` as a static visual specimen; live interaction is verified only inside `lab-popover-pattern.html`. Same posture as ripple v3.3.3 — promotion to baseline theme interaction layer is a separate future Charter §1 decision and is intentionally deferred.
- **`components.css` `.ax-menu` primitive UNCHANGED** — `lab-popover.css` adds only runtime states (positioning, focus ring, reduced-motion override). No drift between baseline visual primitive and lab runtime layer.
- **OUT scope (carried over to future releases)**: tooltip (`createTooltip` / `positionTooltip` / `enableTooltips` — separate `lab-tooltip` module, future release), FAB menu (56px context, v3.4.6 FAB conversion), command palette, dynamic menu registry, editor-side menu builder, ripple baseline promotion, popover baseline promotion.

### Nine issues fixed during refinement (vs. benchmark originals)

1. Global click/keydown/resize/scroll listeners → open-scoped attach/detach (BEER-CSS-INTAKE rule 4 "no global handler").
2. Missing forbidden-ancestor bail-out → explicit `.prose` / `[contenteditable]` check on every trigger event (Charter §5).
3. Incomplete focus restoration (Escape only) → universal restoration on all close paths (Escape, outside-pointerdown, menuitem activation, Tab).
4. `click` → `pointerdown` for outside dismiss (faster, robust across touch / mouse / pen).
5. `stopPropagation` reliance → `requestAnimationFrame`-deferred outside-listener attach. The trigger's pointerdown has already propagated by the time the listener exists, so the open-tick and dismiss-tick cannot collide.
6. Inline SVG chevron in `enableSplitButtonMenus` → Material Symbols `arrow_drop_down` glyph (consistent with v3.4.4 chrome migration).
7. Styleguide-specific selectors (`#components-menu .sg-row > .ax-menu`) → declarative `[data-popover-trigger]` hook.
8. Missing `activeElement` capture → `previousFocus = document.activeElement` stored at open.
9. Inconsistent ARIA triad → enforced and auto-filled at init time (`aria-haspopup`, `aria-expanded`, `aria-controls`, `role="menu"`, `role="menuitem"`).

### BACKLOG resolution

- **#9 (Module pattern theme switcher attribute mismatch)** — closed. The `data-theme-button` → `data-theme-set` rename was applied as Phase 4b of this release across all 4 existing module pattern HTMLs. Theme palette toggling now functions correctly in carousel, ripple, search-expansion, and icon-system pattern pages. The new `lab-popover-pattern.html` uses `data-theme-set` from authoring.
- **#10 (Lab ripple module runtime verification)** — re-enabled. Now that theme palette toggles correctly in module pattern pages (item 9 resolved), ripple in `lab-ripple-pattern.html` can be verified under proper palette conditions. Verification itself remains a separate visual-QA pass; item #10 stays open until that verification is performed.
- **#11 (Public surface reframe)** — added during v3.4.5 mid-discussion. Bucket E, v3.5.0 candidate. Records user vision ("승격된 컴포넌트 모듈의 결과만 styleguide에, 미승격 모듈은 lab-flask popup으로") + GPT consultation (GitHub Pages entry layout, mirror = generated artifact policy, typography-axis adjunct position). Not blocking v3.4.5.

### Verification

1. `python3 tools/generators/publish_styleguide.py` — publish surface now 19 files (12 stylesheets including new `lab-popover.css` + `style-guide.html` + `style-guide-blocks.html` + `style-guide-prose.html` + `scripts/style-guide.js` + 3 mirror notice files). `lab-popover.js` is intentionally NOT in publish surface — lab-internal only.
2. `python3 tools/validators/validate_theme_pilot.py` — Overall 1.000 (PASS): A schema 1.000 / B theme 1.000 / C css 1.000 / D runtime 1.000.
3. Cohort fix verified: all 5 module pattern HTMLs (4 existing + 1 new) show `data-theme-button=0 / data-theme-set>0`.
4. `style-guide.html` carries no reference to `lab-popover.js` (live wiring boundary preserved).

### Forward path

- **v3.4.6** — two candidates, decision deferred:
  - *Tooltip Module Extraction* — extract `createTooltip` / `positionTooltip` / `enableTooltips` (3 functions, ~70+ lines in benchmark) into `lab/modules/tooltip/`. Closes the Beer CSS interaction-module family.
  - *Styleguide UX / Public Surface prep* — BACKLOG #11 (v3.5.0 candidate) preparation work: write `lab/docs/PROMOTION-CRITERIA.md`, write the publish-doc "generated artifact, not authoring surface" note, link `typography-axis.html` from the Typography section.
- **v3.5.0** — BACKLOG #11 Public Surface Reframe — GitHub Pages entry, lab-flask popup for unpromoted modules, styleguide trimming. Depends on v3.4.5 popover (which is now landed — lab-flask popup needs popover runtime).

## v3.4.4.1 — Remaining Chrome SVG Sweep (2026-05-14)

Close-out patch finishing the 24px chrome inline-SVG family that v3.4.4 deliberately scoped down to four slot classes. Five inline SVGs converted to Material Symbols glyph spans in the exact same conversion shape as v3.4.3 / v3.4.4. No policy changes, no audit-number edits to v3.4.4, no scope expansion into other families (FAB, lists, menus, text-fields, brand SVGs all remain untouched).

### Changed

- **`lab/style-guide.html`** — 5 SVGs converted across two host components:
  - `tabs__tab` × 3 (`home`, `explore`, `person`) — bottom-tab icons with sibling Korean label (홈 / 탐색 / 프로필). `tabs__tab is-active` markup preserved on the parent button; only the inner `<svg>` is replaced.
  - `dialog__icon` × 2 (`error`, `error`) — both instances of the save-confirm dialog (one inline static demo at L2838, one portal modal at L3166). Identical SVG content in both locations was replaced with a single `replace()` call (count 2 verified pre-replacement).
- **File size**: `style-guide.html` 183,359 B → 182,947 B (−412 B, −0.2%).
- **Inline `<svg>` count**: 77 → 72 (−5).
- **`material-symbols-rounded` count**: 69 → 74 (+5).

### Added

- **`lab/modules/icon-system/docs/ICON-MIGRATION-PASS-2.1-AUDIT.md`** (~5 KB) — Bucket: D. Records the 5-entry mapping table, two per-slot notes (`탐색` placeholder → semantic upgrade to `explore`; `dialog__icon` shape→glyph mapping rationale for `error`), accessible-name handling pattern (decorative sibling pattern continues from v3.4.3/v3.4.4), 24px visual rhythm checklist (5 manual checks), five-criterion promotion verdict (PASS), and an explicit "what this patch does NOT change" section enumerating that v3.4.4's `ICON-MIGRATION-PASS-2-AUDIT.md` and v3.4.2's `INLINE-SVG-INVENTORY.md` are intentionally not touched.

### Scope discipline

- **Single family only**: 24px chrome inline-SVG migration. The five SVGs belong to the same family as v3.4.4's ten — `tabs__tab` and `dialog__icon` are sibling slot classes to `ax-button-icon` / `chip__leading-icon` / `chip__trailing-icon` / `search-bar__leading-icon`, all rendered in 24px chrome surfaces.
- **NOT touched**: FAB (56px, 35 SVGs), `ax-list`, `ax-menu`, `text-field`, `ax-checkbox`, `ax-progress` (geometric), `ax-loading` (geometric), brand / social / wordmark SVGs, `chip` measurement audit (BACKLOG item 4).
- **Audit-numbers policy**: v3.4.4's audit doc is **deliberately not edited**. The 96-SVG / 50-MS post-state recorded there is correct for v3.4.4 as a release; the further reduction to 72 / 74 is a v3.4.4.1 fact, recorded in its own audit doc.

### Why this didn't go to BACKLOG

The original plan after v3.4.4 was to push these five SVGs to `BACKLOG.md` under a future "v3.4.x Styleguide Chrome Icon Sweep" item. Mid-discussion the call was revised: since the five SVGs are the *same migration family* as v3.4.4's commitment (same conversion shape, same Bucket, same 24px surface, hand-curated mapping), closing them in a same-day patch was cleaner than deferring. Strict guardrail applied: the patch is limited to those five SVGs only; any larger SVG / brand / plugin work remains future-roadmap.

### Verification

1. `python3 tools/generators/publish_styleguide.py` — publish surface still 18 files; `styleguide/index.html` (mirror) reflects the 5 new Material Symbols spans.
2. `python3 tools/validators/validate_theme_pilot.py` — Overall 1.000 (PASS): A schema 1.000 / B theme 1.000 / C css 1.000 / D runtime 1.000.
3. Inline-SVG sweep: `<svg ` count 77 → 72 in lab + 72 in publish mirror (parity).
4. Material Symbols sweep: `material-symbols-rounded` count 69 → 74 in lab + 74 in publish mirror.

### Forward path

- **v3.4.5 (NEXT)** — Anchored Popover / Menu Module remains unchanged. The 24px chrome cohort is now exhausted in `style-guide.html` for the slot classes covered by v3.4.3 / v3.4.4 / v3.4.4.1. FAB, internal-rendering icons, and brand SVGs continue per ROADMAP.

## v3.4.4 — Icon Migration Pass 2 + WordPress SVG Specimen (2026-05-14)

Second end-to-end pass of the inline-SVG → Material Symbols conversion pattern established at v3.4.3. **Scope evolved during Phase 0 inventory**: the v3.4.2 estimate (chip 4 + search-bar 1 + ax-button 10 = 15) was an artifact of the L1500-lookback inventory heuristic counting nested ax-icon-button cases under chip/ax-button host classes. Those nested SVGs had already been converted at v3.4.3. The actual entry-state count was: chip 0, ax-button 5, search-bar 1. The cohort was expanded to include the same-surface-family navigation chrome — nav-bar 4 + nav-rail 9 (with 1 nav-rail item pre-converted before entry) — bringing executed scope to 19 SVGs.

Phase 4 of the release integrates the WordPress wmark specimen into `icon-system-pattern.html` as a `currentColor`-normalized, styleguide-only reference. Caption deliberately scoped down per consultation feedback: **not** "trademark policy compliant", but "official-source, styleguide-only specimen with mandatory trademark caption".

Resolves BACKLOG items 5 (WordPress logo specimen) and 7 (search-bar known delta).

### Changed (Icon Migration Pass 2 — conversion)

- **`lab/style-guide.html`** — 19 inline SVGs converted across five host components:
  - `ax-split-button__trailing-icon` × 4 (`arrow_drop_down` — 더 많은 저장 옵션 / 공유 옵션 / 내보내기 옵션 / 발행 옵션)
  - `ax-button` snackbar close × 1 (`close`)
  - `search-bar__leading-icon` × 1 (`search`)
  - `nav-bar__icon` × 4 (`home`, `search`, `notifications`, `person`)
  - `nav-rail__icon` × 9 (variant 1: `home`, `notifications`, `chat`, `person`; variant 2: `home`, `search`, `notifications`, `chat`, `person`)
- Inline SVG count: 96 → 77 (19 removed).
- 메시지 → `chat` (not `mail`/`forum`); 검색 → `search`; 알림 → `notifications`; 홈 → `home`; 프로필 → `person`. Korean accessible-name labels preserved via visible `<span class="nav-*__label">` text.

### Changed (CSS — wrapper compatibility)

Three `components.css` rules extended with `.material-symbols-rounded` sibling selectors so the new spans receive the same sizing/stacking the previous `> svg` rules provided:

- **`.search-bar__leading-icon > .material-symbols-rounded`** — `font-size: var(--comp-icon-size-md)`
- **`.nav-bar__icon > .material-symbols-rounded`** — `font-size: var(--comp-icon-size-md); position: relative; z-index: 1` (re-establishes the lift above the active-state pill indicator)
- **`.material-symbols-rounded.ax-split-button__trailing-icon`** — `font-size: 20px` (overrides the default 24px to match M3 spec for split-button trailing chevron)

`.nav-rail__icon` did not need a new rule (existing sizing relies on intrinsic content size + the glyph's own 24px default font-size).

### Added (audit doc + reference specimen)

- **`modules/icon-system/docs/ICON-MIGRATION-PASS-2-AUDIT.md`** (~14 KB) — Bucket: D + F. Records the full 19-entry mapping table, the inventory-drift note (15 estimate → 19 actual with the chip 0 / nav-bar 4 / nav-rail 9 discovery), the 3 CSS patches, the 29-check 24px-context visual rhythm QA checklist (split-button 4, snackbar close 3, search-bar leading 4, nav-bar 4, nav-rail 7, WordPress wmark specimen 6, hardening 4), and the five-criterion promotion verdict (A/B/D/E PASS; C PASS pending live styleguide QA pass).

- **`modules/icon-system/icon-system-pattern.html §SVG section`** — WordPress wmark specimen finalized. Original `<style>.cls-1{fill:#32373c;}</style>` + `<defs>` block stripped; each `<path class="cls-1">` rewritten as `<path fill="currentColor">`. Rendered at 56×56 inside a `<figure>` whose wrapper color is bound to `--md-sys-color-on-surface`, so theme-state cascade flips the wmark color automatically (light → dark = currentColor follows on-surface). Caption: *"WordPress logo specimen — shown for SVG interoperability reference only. WordPress and the WordPress logo are trademarks of the WordPress Foundation. This specimen does not imply endorsement or affiliation."* + source link to `wordpress.org/about/logos/`.

### What does NOT change in v3.4.4

```
ax-fab               35 SVGs    → next release (v3.4.6 candidate)
ax-list              8 SVGs     → keep (content-adjacent, not chrome)
ax-menu              7 SVGs     → keep (per-instance leading icons)
text-field           7 SVGs     → keep (plugin territory: icon picker)
ax-checkbox          7 SVGs     → keep (geometric primitives)
ax-progress          5 SVGs     → keep (variable arcs)
ax-loading           4 SVGs     → keep (spinner geometry)
sg-* chrome          4 SVGs     → separate cohort (styleguide-only)
```

77 inline SVGs remain in `style-guide.html`. Chip measurement audit (BACKLOG item 4) explicitly deferred — Material Symbols glyph-box may shift chip rhythm and that's a separate re-measurement exercise.

### BACKLOG.md — items closed

- **#5 WordPress logo styleguide specimen** — closed. Integrated as a `currentColor`-normalized reference specimen in `icon-system-pattern.html`. Not bundled in theme core.
- **#7 search-bar leading icon known-delta** — closed. The search-bar leading magnifier (L2564) is now a Material Symbols `search` glyph, consistent with the rest of the icon-system runtime.

### BACKLOG.md — items still open after v3.4.4

```
#1 inline code font-size inherit
#2 avatar token consistency
#3 floating-toolbar is-selected color
#4 chip M3 measurement audit
#6 monotone SVG theming plugin concept
#8 module pattern cohort fix [resolved in v3.4.3.1, kept for trail]
```

### Validator + publish

- `python3 tools/generators/publish_styleguide.py` — clean run, 11 stylesheets + style-guide.html → index.html + 2 long-form pages + module patterns flattened.
- `python3 tools/validators/validate_theme_pilot.py` — **1.000 (PASS)** across all four axes (A schema, B theme, C css, D runtime).

## v3.4.3 — Icon Button Runtime Prototype (2026-05-14)

First end-to-end implementation of the inline-SVG → Material Symbols conversion pattern established by the v3.4.2 Icon System Scope Audit. Scope deliberately narrow: 40 `ax-icon-button` instances in `style-guide.html` plus the 4 hardening CSS rules in `lab/stylesheets/icons.css`. FAB, button leading icons, chip, search-bar, list, menu, text-field, and checkbox are explicitly NOT touched — each has different size, motion, or layout constraints that need independent visual QA per the conversion ordering in `INLINE-SVG-INVENTORY.md`.

### Created

- **`modules/icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md`** (~11 KB) — per-prototype audit. Bucket: D (theme interaction). Records: the 4 hardening CSS rules now in icons.css with rationale; the 30-entry aria-label → Material Symbols mapping table (covering all 40 instances); the visual QA checklist (29 manual checks across 7 categories — size/alignment, weight/stroke, filled state, dark mode + GRAD, hardening proof, disabled state, accessibility); the five-criterion promotion verdict (PASS on all five, construction-level; manual visual QA on the canonical styleguide pending).

### Changed

- **`lab/stylesheets/icons.css §1`** — base `.material-symbols-rounded` class gains 4 hardening CSS rules with a 25-line rationale block comment referencing `modules/icon-system/docs/ICON-FONT-POLICY.md`:
  - `user-select: none` — disables double-click text selection of the ligature
  - `-webkit-user-select: none` — Safari/WebKit equivalent
  - `-webkit-user-drag: none` — disables Safari force-touch / iOS long-press drag-as-text
  - `pointer-events: none` — pointer events pass through to parent control
- **`lab/style-guide.html`** — 40 `ax-icon-button` blocks converted. Each conversion replaces the inline `<svg>...</svg>` element with `<span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true" draggable="false">GLYPH</span>`, preserving the parent button's `class`, `type`, and `aria-label`. File size 186,659 B → 182,483 B (−4,176 B, −2.2%). Total inline SVG count in file 146 → 106 (40 removed, matches `INLINE-SVG-INVENTORY.md §Counts` row for `ax-icon-button`). `ax-icon-button` blocks with `material-symbols-rounded` 0 → 40; `ax-icon-button` blocks with inline `<svg>` 40 → 0. The conversion mapping is recorded in the audit's mapping table for audit trail.

### Mapping summary

30 distinct aria-labels covered all 40 button instances. Full table in `ICON-BUTTON-RUNTIME-AUDIT.md §Mapping table`. Highlights:

- **Korean preserved**: `이전 달` → `chevron_left`, `다음 달` → `chevron_right`. Korean text stays in `aria-label` on the parent button; the glyph is the universal chevron.
- **`favorite` for both "Favorite" and "Like"** — semantic distinction lives in `aria-label`, not the glyph (single M3 heart glyph).
- **`mail` for all "Messages*" variants** — baseline glyph; badge counters carry unread-count semantics. Variant glyphs (`mark_email_unread`, etc.) deferred until visual distinction becomes important.
- **`notifications` for both unread / (3) variants** — same logic.
- **`close` for both "Clear" and "Close"** — same M3 X-glyph; aria-label disambiguates ("Clear" = text-field clear button, "Close" = dialog close).
- **`more_vert` for "More"** — vertical 3-dot overflow per M3 spec (matches original SVG's vertical circle arrangement).

### Architectural decisions

- **Mapping is explicit, not heuristic** — 30 entries hand-curated against the M3 glyph catalog and the original SVG path fingerprints. A heuristic "convert SVG path to glyph name" approach would be unreliable (same glyph has multiple legitimate path representations) and would produce an unauditable mapping. The explicit table doubles as the audit trail; any glyph swap in the future has a single edit point.
- **Hardening CSS lives in the canonical theme, not in the module** — the `material-symbols-rounded` class is theme-shipped (Bucket D); the hardening is its baseline contract. Putting hardening inside the icon-system module would split the contract across two files and require module load order to enforce it. Instead, the contract specification stays in `modules/icon-system/docs/ICON-FONT-POLICY.md` and the implementation lives in `lab/stylesheets/icons.css §1` next to the base class declaration.
- **Conversion stops at `ax-icon-button`** — FAB (35), button leading icons (10), chip (4), and search-bar (1) all use the same conversion shape, but each has independent visual rhythm concerns (FAB is 56px not 24px; button leading icons sit next to text and need baseline alignment with text; chip leading icons are 18px not 24px; search-bar leading icon shares space with the input and trailing slots). Per `INLINE-SVG-INVENTORY.md §Conversion ordering`, these are v3.4.4 work.
- **Audit trail policy for inventory document** — `INLINE-SVG-INVENTORY.md` was authored at v3.4.2 as a pre-conversion snapshot. v3.4.3 does NOT modify it. After v3.4.3 the inventory is partial-history rather than current-state; running the audit script again produces a fresh count showing 106 SVGs remaining (down from 146). A future release can append an "as-of vX.Y.Z" snapshot section to the inventory doc.

### Tools run

1. `python3 tools/generators/publish_styleguide.py` — publish surface still 18 files. `styleguide/index.html` (mirror of `style-guide.html`) confirmed to contain 40 `material-symbols-rounded` occurrences. `styleguide/stylesheets/icons.css` (mirror) confirmed to contain the 4 hardening rules.
2. `python3 tools/validators/validate_theme_pilot.py` — **1.000 / 1.000 PASS** across all four axes.
3. Mapping table coverage verified — 40/40 buttons in HTML are covered by the 30-entry mapping table; no orphan aria-labels.
4. Post-conversion sanity — 0 `ax-icon-button` blocks still contain inline `<svg>` (was 40); 40 `ax-icon-button` blocks now contain `<span class="material-symbols-rounded">` (was 0).

### What this unblocks

- **v3.4.4 chip / search-bar / button leading-icon conversion** — the same conversion pattern, applied to 15 more SVGs (chip 4 + search-bar 1 + ax-button 10). The mapping table format established here is the template for the v3.4.4 audit.
- **v3.4.5 popover module** — popover triggers in chrome can use Material Symbols glyphs from day one (no SVG legacy to migrate). Menu-item leading icons can also use the icon font directly.
- **`axismundi-icons` plugin future extraction** — the plugin's "Material Symbols tab" picker is now backed by a real running showcase (40 examples across the canonical styleguide). Plugin authors have a working reference for the markup pattern.
- **FAB conversion (v3.4.6 candidate)** — when ready, `ax-fab` follows the same pattern but at larger size (56px). FAB has 35 SVGs across multiple variants (FAB, Extended FAB, FAB menu); independent visual QA pass.

### What this does NOT do

- Does not modify `components.css §G` (or wherever `ax-icon-button` is defined). The conversion is HTML-level only.
- Does not touch any module's CSS / JS (`modules/carousel/`, `modules/ripple/`, `modules/search-expansion/`, `modules/icon-system/icon-system-pattern.html`).
- Does not change `style-guide-blocks.html`, `style-guide-prose.html`, `style-guide-benchmark.html`. The 106 remaining inline SVGs in `style-guide.html` (FAB, chrome, list, menu, text-field, checkbox, progress, chip, search, uncategorized) are unchanged.
- Does not change `publish_styleguide.py` or `validate_theme_pilot.py`.
- Does not promote the `ax-icon-button` runtime out of lab; it is still part of baseline `style-guide.html`. The "prototype" label refers to the conversion pattern being run for the first time at production scale, not to a module promotion event.

### Statistics

| File | Status | Size delta |
|---|---|---|
| `lab/stylesheets/icons.css` | hardening added | +~1.4 KB |
| `lab/style-guide.html` | 40 SVGs converted | −4,176 B |
| `modules/icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md` | new (audit + mapping + QA checklist) | ~11 KB |
| `styleguide/stylesheets/icons.css` | publish mirror updated | matches lab |
| `styleguide/index.html` | publish mirror updated | matches lab |

---

## v3.4.2 — Icon System Scope Audit (2026-05-14)

First cross-cutting module under the v3.4.1 charter. **Scope audit only** — minimal implementation, mostly inventory + policy. The icon system is dual-engine by design: Material Symbols variable icon font for M3 chrome glyphs (Bucket D, theme interaction), and SVG icons for WordPress / editor / social / brand / portable content (Bucket F, plugin territory). These tracks are complementary, not alternatives.

### Created — `lab/modules/icon-system/`

- **`docs/ICON-SYSTEM-AUDIT.md`** (~9 KB) — umbrella audit. Bucket distribution per charter §3 (D-runtime + F-plugin). TL;DR of the dual-engine decision. What this release does and does NOT do (no inline-SVG conversion in this release; conversion order is v3.4.3 work). Promotion criteria at policy level (not per-component, since icon system spans 12 components). Lineage references to Material Symbols, `@wordpress/icons`, Social Icons, and Icon Block plugin.

- **`docs/ICON-FONT-POLICY.md`** (~11 KB) — Material Symbols hardening contract. Five mandatory markup attributes (`class="material-symbols-rounded"`, `class="notranslate"`, `translate="no"`, `aria-hidden="true"`, `draggable="false"`) with per-attribute rationale. Four CSS hardening rules (`user-select: none`, `-webkit-user-select: none`, `-webkit-user-drag: none`, `pointer-events: none`) — CSS implementation deferred to v3.4.3 but the contract is locked here. Variable-axes contract (FILL / wght / GRAD / opsz) including the v3.2.1 GRAD-shared-with-text nuance: `--md-grade` is theme-scope and MUST NOT be exposed per-block in any future picker. Rounded-only-ships-with-theme decision recorded with rationale. Failure modes classified per charter §7 (allowed: font fails to load; forbidden: glyph drags out as text). Optional inline-SVG fallback pattern documented.

- **`docs/SVG-ICON-POLICY.md`** (~9 KB) — when SVG is required (not optional). Eight required cases (WordPress block editor icon, `core/social-link` variations per WP 6.9, brand logos, Icon Block plugin parity, content-portable icons, federated content, email rendering). Three permitted cases. Three forbidden cases (script-bearing SVG, `<foreignObject>` HTML in federation, content-prose icon-font usage). WordPress integration points specified — `@wordpress/icons` JSX shape, `core/social-link` variation registration, Icon Block parity, `theme.json` icons. Sanitization baseline (8-row policy table). Accessibility patterns for decorative / semantic / brand SVG. `fill="currentColor"` guidance with brand-color exception.

- **`docs/INLINE-SVG-INVENTORY.md`** (~7 KB) — 146-SVG hard-data inventory across the three canonical styleguide HTML files. Categorized by enclosing component class: `ax-icon-button` 40 (27%), `ax-fab` 35 (24%), `sg-*` chrome 21 (14%), `ax-button` 10, `ax-list` 8, `ax-menu` 7, `text-field` 7, `ax-checkbox` 7, `ax-progress` 5, `chip` 4, `search-bar` 1, uncategorized 1. Per-category decisions: convert chrome glyphs to icon font (`ax-icon-button`, `ax-fab`, etc.); keep as SVG for component-internal rendering (`ax-checkbox` check-marks, `ax-progress` arcs that need variable percentages). Conversion ordering for v3.4.3 specified (icon-button first as the highest-volume + lowest-risk pattern). Audit script included for reproducibility.

- **`docs/ICON-PICKER-UX.md`** (~9 KB) — sketch for the future `axismundi-icons` plugin. Three surfaces: ToolbarItem for quick insert / toggle / replace, Sidebar Inspector for variable-axis adjustment (FILL / wght / opsz only — `--md-grade` excluded per charter §2), and a Popover for browsing the icon registry. Icon registry schema sketched with Korean-multilingual tags example. Two-tab picker (Material Symbols / Custom SVG) for the dual-engine. Type override mechanism (Material Symbols / Custom SVG / Inline string) for power users. Federation behavior decoupled (storage is one choice; render fallback is per-block-type). Plugin territory boundary made explicit (what picker does and does NOT do). Four open questions deferred to plugin-extraction time.

- **`icon-system-pattern.html`** (~14 KB) — minimal pattern page with eight sections: Material Symbols glyph showcase (8 chrome glyphs), variable axes demo (FILL × weight matrix on `favorite`), composed inside chrome components (`ax-icon-button`, `ax-button`, `chip` with proper hardening markup), SVG-required examples (WordPress + Mastodon brand glyphs), hardening proof checklist (6 manual tests), forbidden scope negative-test case (Material Symbols glyph inside `.prose` — verifies prose.css §12 scope enforcement still works). The hardening CSS rules are applied **inline on this page only** as a demonstration; they move into `lab/stylesheets/icons.css` in v3.4.3 as the canonical theme runtime.

### Changed

- **`lab/modules/README.md`** — module inventory table extended with `icon-system/` row labeled "Lab-internal; scope audit + 5 policy docs; runtime conversion deferred to v3.4.3".

### Architectural decisions

- **Dual-engine, not alternatives** — picking icon font OR SVG would either cripple M3 chrome (no variable-axis glyphs) or break WordPress ecosystem interoperability (no `@wordpress/icons` parity, no Social Icons block compatibility, no custom social SVG via WP 6.9 `core/social-link` variations, no Icon Block plugin compatibility). Both engines must coexist; their boundary follows the charter §1 / §3 / §4 split.

- **`--md-grade` is theme-scope, not block-scope** — v3.2.1 introduced `--md-grade` as a custom property shared between text typography (Roboto Flex GRAD axis) and icon font (Material Symbols GRAD axis) so that dark-mode `--md-grade: -25` adjustment applies to both in lockstep. Any future plugin-side axis picker MUST NOT expose `--md-grade` per-block — only FILL, weight, optical size are block-scope. Codified in both `ICON-FONT-POLICY.md` and `ICON-PICKER-UX.md`.

- **Hardening rules defined here, implemented next release** — `ICON-FONT-POLICY.md §Required CSS hardening` specifies the four CSS rules (`user-select: none`, `-webkit-user-select: none`, `-webkit-user-drag: none`, `pointer-events: none`) but does not add them to `lab/stylesheets/icons.css` in this release. Reason: the rules become visually testable on a real component, so they belong with the first conversion (v3.4.3 icon button runtime prototype) where they can be verified end-to-end. The pattern page applies the rules inline as a demonstration.

- **Conversion deferred, not pre-empted** — converting 146 inline SVGs to Material Symbols glyphs in a single pass would destabilize visual rhythm before per-component QA finishes. `INLINE-SVG-INVENTORY.md §Conversion ordering` specifies the per-category order (ax-icon-button first, sg-* chrome last) and identifies categories that should NEVER convert (`ax-checkbox` check-mark animations, `ax-progress` variable-percentage arcs).

- **Korean-first authoring respected in picker design** — `ICON-PICKER-UX.md` mandates that the icon registry's `tags` field include Korean equivalents (`검색`, `찾기`, `홈`, `메뉴`) and that the picker's search accepts mixed-script input. Matches the project's existing Korean-first text-field demos and typography axis showcase.

### Tools run

1. `python3 tools/generators/publish_styleguide.py` — publish surface still 18 files (no module CSS added; icon-system is documentation-only). Validation that the publisher's `glob('modules/*/lab-*.css')` correctly returns 3 (carousel, ripple, search-expansion) and not 4 — the icon-system module has no `lab-*.css` file in this release.
2. `python3 tools/validators/validate_theme_pilot.py` — **1.000 / 1.000 PASS** across all four axes. Sanity check; pilot validator is unaffected by lab module documents.
3. Relative-path resolution check on `icon-system-pattern.html` — all 11 references resolve to existing files in the lab tree.

### What this unblocks

- **v3.4.3 Icon Button Runtime Prototype** — implements the 4 CSS hardening rules in `lab/stylesheets/icons.css` and converts the 40 `ax-icon-button` inline SVGs to Material Symbols glyphs as the first end-to-end test of the conversion pattern. The contract for what to do is already in `ICON-FONT-POLICY.md` and the conversion order is already in `INLINE-SVG-INVENTORY.md`.
- **v3.4.x `axismundi-icons` plugin extraction** — when the plugin is built, its picker UX is already specified in `ICON-PICKER-UX.md`, its required SVG cases in `SVG-ICON-POLICY.md`, and its hardening contract in `ICON-FONT-POLICY.md`. The plugin can be built against a stable contract without re-deriving the dual-engine design.
- **Future Popover (v3.4.4 or later) menu-item icons** — popover module can cite `ICON-FONT-POLICY.md` for menu-item leading-icon markup pattern instead of inventing one.
- **Future Social Icons / brand work** — `SVG-ICON-POLICY.md` already specifies the `core/social-link` variation pattern and sanitization baseline.

### What this does NOT do

- Does not convert any inline SVG to Material Symbols. (Inventory only.)
- Does not modify `lab/stylesheets/icons.css` or `lab/stylesheets/fonts.css`. (Existing infrastructure is already correct; only the missing hardening CSS rules are pending v3.4.3.)
- Does not implement any picker UI. (Plugin territory; deferred until `axismundi-icons` plugin extraction.)
- Does not change `theme.json`, register any blocks, or modify the canonical styleguide HTML.
- Does not modify `publish_styleguide.py` (it correctly picks up zero module CSS files from icon-system because the module is documentation-only this release).

### Statistics

| File | Status | Size |
|---|---|---|
| `modules/icon-system/docs/ICON-SYSTEM-AUDIT.md` | new | 9,012 B |
| `modules/icon-system/docs/ICON-FONT-POLICY.md` | new | 11,290 B |
| `modules/icon-system/docs/SVG-ICON-POLICY.md` | new | 9,407 B |
| `modules/icon-system/docs/INLINE-SVG-INVENTORY.md` | new | 7,116 B |
| `modules/icon-system/docs/ICON-PICKER-UX.md` | new | 9,326 B |
| `modules/icon-system/icon-system-pattern.html` | new | 14,178 B |
| `modules/README.md` | inventory updated | +1 row |
| Code / `icons.css` / `fonts.css` / publish surface | **unchanged** | — |

---

## v3.4.1 — Architecture Boundaries Charter (2026-05-14)

Pure governance release. **Zero code changes.** Establishes the project-wide architectural charter that subsequent v3.4.x modules (icon system, popover, TOC, date/time, ActivityPub) will reference instead of re-deriving the same layer-classification boundaries each time. Triggered by the cumulative weight of four upcoming work items all asking the same boundary questions: *is this baseline? module? theme interaction? plugin? may this enter post_content or federated surfaces?*

### Created

- **`products/reference-implementations/axismundi-lab/docs/ARCHITECTURE-BOUNDARIES.md`** (~14 KB) — the charter. Nine sections:

  - **§1 Four layers** — formal definitions of *baseline styleguide*, *lab module*, *theme interaction*, and *plugin*. A lab module exists when an M3 component, interaction, or authoring pattern cannot be fully represented by WordPress core blocks alone — but can still be explored through a bounded lab surface before being either promoted to baseline, dispatched to a theme interaction, or escalated to a plugin.

  - **§2 Theme state vs theme control** — the most important new rule. *Theme state is global; theme controls are chrome-only.* The selected theme mode cascades into prose / post content via design tokens; the theme toggle UI itself must remain in header / footer / navigation / settings chrome and never enter post_content, comments, excerpts, or federated content. Stated in English + Korean, with an allowed-cascade CSS example and a forbidden-intrusion HTML example. Generalizes to any future state system (density, reading width, etc.).

  - **§3 Bucket reclassification (7 categories)** — A (core block direct), B (core block + style variation), C (core block + pattern), D (theme interaction enhancement), E (lab module / plugin candidate), F (plugin territory), G (excluded / archive). Working classification table covers 19 items spanning current modules, near-term work, and future plugin territories. Mandates that every per-module audit from v3.4.1 onward include a `Bucket:` field; existing audits (carousel, ripple, search-expansion) get the field appended retroactively when next revised.

  - **§4 Theme can / Plugin should** — concrete lists. Theme can: style core blocks, register patterns, register style variations, define template parts, enqueue progressive interaction, provide slots, render baseline M3 glyph system. Plugin should: register durable custom blocks (those whose `name` is saved to post content), host editor UI, persist non-WordPress schema, parse content, integrate external protocols. Includes the block-registration nuance: theme may temporarily register a custom block, but the durable owner should be a plugin (because block names are stored in post content and migration is expensive). Codifies that `axismundi-pilot` will register zero custom blocks.

  - **§5 Forbidden ancestor list** — `.prose`, `.wp-block-post-content`, `.entry-content`, `[contenteditable]`. **Locked in lockstep with `BEER-CSS-INTAKE.md` §1** — both documents must update together when the list changes. Distinguishes "interactive controls forbidden" (the rule) from "state cascading through CSS variables" (the §2 exception).

  - **§6 Federation portability** — *If content will be serialized into ActivityPub, RSS, an excerpt, or any remote-client view, it must not depend on theme-only JS, ligature icon fonts, or private CSS class semantics.* Includes a 7-row property-vs-context matrix (theme JS, icon font ligature, private classes, carousel JS, ripple, Material Symbols glyph, inline SVG icon) showing what is allowed where. Reinforces why the prose §12 icon-scope policy added in v3.2.1 was necessary, and why federation extends that constraint further.

  - **§7 Frontier-theme failure-mode policy** — Axismundi is positioned as a frontier theme; modern CSS is allowed, progressive enhancement is preferred over legacy compatibility. *Allowed failure modes*: visual enhancement missing, optional motion absent, decorative chrome simplified, subtle layout regression. *Forbidden failure modes*: content inaccessible, controls unusable, layout destroys reading, focus lost, federation breaks. Notes equivalence: passing the five-criterion module promotion check ↔ no forbidden failure modes.

  - **§8 Pattern documentation UX** — practical recommendation discovered during carousel and confirmed by v3.4.0 restructure: canonical styleguide holds *final demos*; lab modules hold *rationale, ontology, fallback, QA*. Example shows the canonical styleguide linking out to a module's pattern HTML rather than inlining the full rationale. Recommendation for v3.4.x+; existing modules can retrofit incrementally.

  - **§9 Living document policy** — charter is living; new clauses may be added for new layers, new failure modes, new ancestor / selector boundaries. Existing clauses change only with explicit change-log entries.

### Changed

- **`docs/BEER-CSS-INTAKE.md`** — top-of-file note added: this contract is the Beer-CSS-specific elaboration of `ARCHITECTURE-BOUNDARIES.md`; forbidden-ancestor list now formally locked to charter §5; component-scope allowlist (§1) reframed in charter bucket terms (D-bucket constraint). New change-log entry: "v3.4.1 — charter alignment. No rule changes." Lockstep documented.

- **`modules/README.md` Cross-module documents section** — now lists `ARCHITECTURE-BOUNDARIES.md` first, labeled as "project charter (v3.4.1+)" with a one-line summary of each charter section name, and a "Read this first" note for module authors.

- **`modules/README.md` Adding a new module section** — step 1 of the procedure is now "Consult `../docs/ARCHITECTURE-BOUNDARIES.md` first" — decide bucket (A/B/C → not a module; D → maybe theme interaction without module; E → module; F → plugin; G → archive). Step 2 (audit against `BEER-CSS-INTAKE.md` or applicable reference) follows. Audit `Bucket:` field requirement called out in step 4.

### Tools run

1. `python3 tools/validators/validate_theme_pilot.py` — **1.000 / 1.000 PASS** across all four axes. Sanity check: charter is a doc-only release, but the validator was run anyway to confirm nothing was accidentally perturbed.

### What this unblocks

- **v3.4.2 Icon System Scope Audit** — first cross-cutting module that needs the charter. Icon font (theme chrome glyphs) and SVG icons (WordPress editor / social / brand / portable content) are a dual-engine system, and the charter's §4 theme-can/plugin-should split is what cleanly separates the two halves. The audit will cite §1 (icon font = theme interaction layer; picker = plugin), §3 (icon system items distributed across buckets D and F), §5 (icon font forbidden inside prose / post content / federated content already documented by §6 federation portability), and §7 (Material Symbols not being loaded = visual enhancement missing = allowed failure).

- **v3.4.3 Anchored Popover / Menu Module** — popover triggers in chrome (D); menu registry, command palette, complex menu builder (F). Forbidden-ancestor bail-out reuses charter §5.

- **v3.4.5 TOC Module Scope Audit** — `.ax-toc-slot` template slot (C/D); heading parsing, anchor generation, scrollspy, editor controls (F). The "TOC slot = theme; TOC generation = plugin" split is a textbook §4 application.

- **v3.4.x Pilot Block Theme Probe** — pilot will register zero custom blocks per §4's block-registration nuance. Pilot's job is to demonstrate that everything in buckets A/B/C/D/E composes correctly without F-bucket dependencies.

- **Future ActivityPub work** — entirely in §6 federation territory; charter sets up the constraint vocabulary before the work begins.

### What this does NOT do

- Does not change any code, CSS, JS, or HTML.
- Does not change `validate_theme_pilot.py` or `publish_styleguide.py`.
- Does not retroactively update bucket fields in existing module audits (those will be appended when each audit is next revised; see §3).
- Does not change any module's promotion status — the five-criterion check is unchanged, just reframed as "passing it == no forbidden failure modes".
- Does not split `components.css` — the future-split-map idea is noted but deferred per the v3.4.x roadmap.

### Statistics

| File | Status | Size |
|---|---|---|
| `docs/ARCHITECTURE-BOUNDARIES.md` | new (charter) | ~14,000 B |
| `docs/BEER-CSS-INTAKE.md` | charter-aligned (top note + v3.4.1 change-log entry) | +1 KB |
| `modules/README.md` | charter-aligned (cross-module docs + onboarding step 1) | +1 KB |
| `ROADMAP.md` | sequence updated | minor |
| Code / styleguide / publish surface / validator output | **unchanged** | — |

---

## v3.4.0 — Lab Module Restructure (2026-05-14)

Pure structural release. No new features, no module extractions. Three lab modules (`carousel`, `ripple`, `search-expansion`) accumulated as flat files from v3.3.2 to v3.3.4 are consolidated into a per-module folder layout under `lab/modules/`. The publish generator (`tools/generators/publish_styleguide.py`) becomes module-aware so it flattens module CSS into the publish surface from the new location. Triggered by the upcoming v3.4.1 popover module — popover is structurally complex (focus trap, anchor positioning, outside-click dismiss, ARIA `aria-haspopup` triad) and would have made the flat layout unwieldy as the fourth member. Better to fix the structure first.

### Lab folder structure: before → after

```
# Before (flat, v3.3.4 state)
lab/
├─ stylesheets/lab-carousel.css       ← interleaved with design-system files
├─ stylesheets/lab-ripple.css
├─ stylesheets/lab-search-expansion.css
├─ scripts/lab-carousel.js
├─ scripts/lab-ripple.js
├─ scripts/lab-search-expansion.js
├─ lab-carousel-pattern.html          ← at lab root level
├─ lab-ripple-pattern.html
├─ lab-search-expansion-pattern.html
└─ docs/
   ├─ CAROUSEL-{AUDIT,ONTOLOGY-CHECK,VISUAL-QA}.md
   ├─ RIPPLE-AUDIT.md
   ├─ SEARCH-EXPANSION-AUDIT.md
   ├─ BEER-CSS-INTAKE.md              ← cross-module, stayed at lab/docs/
   └─ INTERACTION-AUDIT.md            ← cross-module historical

# After (v3.4.0)
lab/
├─ stylesheets/                       ← only design-system files now
│  ├─ tokens.css, base.css, components.css, blocks.css,
│  ├─ prose.css, fonts.css, icons.css, benchmark-interactions.css
├─ scripts/                           ← only design-system scripts now
│  ├─ style-guide.js, theme.js, benchmark-interactions.js
├─ docs/                              ← only cross-module docs now
│  ├─ BEER-CSS-INTAKE.md
│  └─ INTERACTION-AUDIT.md
├─ modules/
│  ├─ README.md                       ← new — explains structure + lab-prefix rule
│  ├─ carousel/
│  │  ├─ lab-carousel.css
│  │  ├─ lab-carousel.js
│  │  ├─ lab-carousel-pattern.html
│  │  └─ docs/
│  │     ├─ CAROUSEL-AUDIT.md
│  │     ├─ CAROUSEL-ONTOLOGY-CHECK.md
│  │     └─ CAROUSEL-VISUAL-QA.md
│  ├─ ripple/
│  │  ├─ lab-ripple.css
│  │  ├─ lab-ripple.js
│  │  ├─ lab-ripple-pattern.html
│  │  └─ docs/RIPPLE-AUDIT.md
│  └─ search-expansion/
│     ├─ lab-search-expansion.css
│     ├─ lab-search-expansion.js
│     ├─ lab-search-expansion-pattern.html
│     └─ docs/SEARCH-EXPANSION-AUDIT.md
└─ (style-guide.html, style-guide-blocks.html, style-guide-prose.html,
    style-guide-benchmark.html unchanged)
```

### Why retain the `lab-<name>` prefix on files inside `modules/<name>/`

A naïve naming scheme (`modules/carousel/module.css`, `modules/ripple/module.css`, etc.) was considered and rejected. The decisive constraint is **publish-surface flattening**: `publish_styleguide.py` flattens module CSS into the top-level `styleguide/stylesheets/` directory next to design-system files like `tokens.css` and `components.css`. With generic names, modules would collide; with the `lab-` prefix retained, each module file remains identifiable both in the source tree (where the folder gives the same information) and on the publish surface (where folder structure is gone). Secondary benefit: IDE tab bars don't fill up with identical `module.css` tabs. Documented permanently in `lab/modules/README.md`.

### Files moved (15)

| From (v3.3.4 location) | To (v3.4.0 location) |
|---|---|
| `lab/stylesheets/lab-carousel.css` | `lab/modules/carousel/lab-carousel.css` |
| `lab/stylesheets/lab-ripple.css` | `lab/modules/ripple/lab-ripple.css` |
| `lab/stylesheets/lab-search-expansion.css` | `lab/modules/search-expansion/lab-search-expansion.css` |
| `lab/scripts/lab-carousel.js` | `lab/modules/carousel/lab-carousel.js` |
| `lab/scripts/lab-ripple.js` | `lab/modules/ripple/lab-ripple.js` |
| `lab/scripts/lab-search-expansion.js` | `lab/modules/search-expansion/lab-search-expansion.js` |
| `lab/lab-carousel-pattern.html` | `lab/modules/carousel/lab-carousel-pattern.html` |
| `lab/lab-ripple-pattern.html` | `lab/modules/ripple/lab-ripple-pattern.html` |
| `lab/lab-search-expansion-pattern.html` | `lab/modules/search-expansion/lab-search-expansion-pattern.html` |
| `lab/docs/CAROUSEL-AUDIT.md` | `lab/modules/carousel/docs/CAROUSEL-AUDIT.md` |
| `lab/docs/CAROUSEL-ONTOLOGY-CHECK.md` | `lab/modules/carousel/docs/CAROUSEL-ONTOLOGY-CHECK.md` |
| `lab/docs/CAROUSEL-VISUAL-QA.md` | `lab/modules/carousel/docs/CAROUSEL-VISUAL-QA.md` |
| `lab/docs/RIPPLE-AUDIT.md` | `lab/modules/ripple/docs/RIPPLE-AUDIT.md` |
| `lab/docs/SEARCH-EXPANSION-AUDIT.md` | `lab/modules/search-expansion/docs/SEARCH-EXPANSION-AUDIT.md` |
| (15th: created) | `lab/modules/README.md` (new, structure-rationale document) |

Each file copied with `md5sum` verification, then the source removed only after byte-identical confirmation.

### Relative-path rewrites in pattern HTML (3 files × ~12 refs)

Each pattern HTML moved two folder levels deeper, so internal references were rewritten:

- Design-system stylesheets: `stylesheets/fonts.css` → `../../stylesheets/fonts.css` (and same for `icons.css`, `tokens.css`, `base.css`, `components.css`, `prose.css`)
- Shared scripts: `scripts/theme.js` → `../../scripts/theme.js`
- The module's own CSS/JS: `stylesheets/lab-ripple.css` → `lab-ripple.css` (now a sibling in the module folder)
- Cross-styleguide nav links: `style-guide.html` → `../../style-guide.html`, `style-guide-benchmark.html` → `../../style-guide-benchmark.html`
- Cross-module nav links: `lab-carousel-pattern.html` → `../carousel/lab-carousel-pattern.html`, and the equivalent for `ripple` and `search-expansion`

All 36 relative-path references across the three pattern HTML files resolve to existing files after the rewrites (verified programmatically by `pathlib.Path.resolve()` against the lab tree).

### Cross-reference rewrites in module CSS / JS / pattern HTML headers (9 files × 1–2 refs each)

Each module's own files reference `docs/BEER-CSS-INTAKE.md` in their header comments. After the move, this cross-module document is no longer in a sibling `docs/` folder — it's in `lab/docs/`, two levels up. The path text in each affected file's comments was rewritten from `docs/BEER-CSS-INTAKE.md` to `../../docs/BEER-CSS-INTAKE.md`. Same rewrite applied to the two audit docs that reference it (`modules/ripple/docs/RIPPLE-AUDIT.md`, `modules/search-expansion/docs/SEARCH-EXPANSION-AUDIT.md`); the audit-doc path becomes `../../../docs/BEER-CSS-INTAKE.md` because audit docs are nested one level deeper than the module-root files.

The per-module audit reference (e.g. `Audit: docs/RIPPLE-AUDIT.md`) inside a module's CSS/JS header **stays the same path-text** — `docs/` resolves to the module's own sibling `docs/` folder both before and after the move. The convention works in both layouts.

### Benchmark EXTRACTED marker path updates (15 refs across 2 files)

`benchmark-interactions.css` and `benchmark-interactions.js` contain marker comments like `EXTRACTED: v3.3.2 → stylesheets/lab-carousel.css` from previous extraction rounds. All 15 such references (9 in CSS, 6 in JS) were updated to point at the new module-folder paths, e.g. `EXTRACTED: v3.3.2 → modules/carousel/lab-carousel.css`. Sorted by string length (longest first) before find-and-replace to avoid partial-match contamination (e.g. `lab-carousel.css` substring inside `lab-carousel-pattern.html`).

### Publish generator update (`tools/generators/publish_styleguide.py`)

Step 1 of the publisher now discovers stylesheets in two places: the existing `lab/stylesheets/*.css` glob plus a new `lab/modules/*/lab-*.css` glob. Both are flattened into `publish/stylesheets/`. The depth-5 asset-path rewrite is added defensively (modules are one folder level deeper than design-system files; the existing depth-4 rewrite is retained for compatibility, and the depth-5 variant `../../../../../core/design-systems/material3/assets/` rewrites to the same `../../core/...` target). Module CSS files do not currently reference these asset paths, but the defensive rewrite ensures any future module that does won't break the publish step.

Module JS, pattern HTML, and per-module docs are still NOT copied to the publish surface — the lab-internal asymmetry that has existed since v3.3.2 is preserved by design. Documented in the publisher's module docstring + in `lab/modules/README.md §What gets published, what stays lab-internal`.

### Tools run

1. Hash-verified copy of all 14 files from flat lab to per-module folders (`md5sum` parity required before old-location remove).
2. `python3 tools/generators/publish_styleguide.py` — 18-file publish surface (was 18 at v3.3.4 — count unchanged because all 3 module CSS files were already on publish surface; only their lab source paths moved).
3. `python3 tools/validators/validate_theme_pilot.py` — **1.000 / 1.000 PASS** across all four axes.
4. `node -c` on all 3 module JS files — syntax OK.
5. Programmatic relative-path resolution check on all 3 pattern HTML files — 36/36 references resolve.

### What this unblocks

- **v3.4.1 Anchored Popover module** can be authored directly under the new structure (`lab/modules/popover/`). No further tooling work needed — the publish generator's module-discovery glob picks up any new `lab/modules/*/lab-*.css`.
- Future modules (date/time interaction audit, etc.) follow the same pattern.
- The procedure documented in `lab/modules/README.md §Adding a new module` is now the canonical onboarding doc for module authors.

### What this does NOT do

- Does not modify any module's behavior, CSS, or JS — content is byte-identical to v3.3.4 except for the relative-path rewrites enumerated above.
- Does not change the canonical `style-guide.html`, `style-guide-blocks.html`, `style-guide-prose.html`, `style-guide-benchmark.html`, or any design-system stylesheet.
- Does not change `validate_theme_pilot.py`.
- Does not delete anything from `benchmark-interactions.{css,js}` — the EXTRACTED comment blocks just got their target paths updated; the original code remains as audit trail (benchmark prune still deferred to v3.4.x / v3.5.0).
- Does not promote any module into `components.css` — promotion is a separate decision per module, not a structural concern.

### Statistics

| File | Status |
|---|---|
| `lab/modules/` (new directory) | Created |
| `lab/modules/README.md` | Created (rationale doc, ~3.7 KB) |
| `lab/modules/carousel/` (6 files) | Populated via move |
| `lab/modules/ripple/` (4 files) | Populated via move |
| `lab/modules/search-expansion/` (4 files) | Populated via move |
| `lab/stylesheets/` | Reduced from 11 to 8 files (3 module CSS removed) |
| `lab/scripts/` | Reduced from 5 to 2 files (3 module JS removed) |
| `lab/` root | Reduced by 3 files (3 pattern HTML removed from root) |
| `lab/docs/` | Reduced from 7 to 2 files (5 per-module docs moved to module folders) |
| `lab/docs/BEER-CSS-INTAKE.md` | Cross-reference table paths updated |
| `lab/stylesheets/benchmark-interactions.css` | 9 path references in EXTRACTED markers updated |
| `lab/scripts/benchmark-interactions.js` | 6 path references in EXTRACTED markers updated |
| `tools/generators/publish_styleguide.py` | Module-aware discovery added |

---

## v3.3.4 — Search Expansion Module Extraction (2026-05-13)

Third module extraction, second under the `docs/BEER-CSS-INTAKE.md` contract. The benchmark `.search-bar` focus expansion + suggestions popup pattern (`benchmark-interactions.css` L181–262 + `benchmark-interactions.js` `enableSearchBar()`) becomes a self-contained lab module. Validates that the intake contract works for patterns with a per-instance state machine (search has per-bar ARIA wiring + popup id + Escape state), distinct from ripple's stateless / delegated-listener shape.

### Created (module)

- **`stylesheets/lab-search-expansion.css`** (~6.4 KB) — refactored Axismundi-native focus expansion. Six hardcoded magic numbers tokenized (gap, padding, min-height, etc.) — three as `var(--space-*)` tokens and three as module-local custom properties at `:root` with rationale comments (`--ax-search-suggestions-item-gap`, `--ax-search-suggestions-item-icon-gap`, `--ax-search-suggestions-item-min-height`). The `44px` minimum height is retained as a documented WCAG 2.5.5 Target Size floor, not a design token. Suggestion items now use a Pattern A `::before` state layer with `--md-sys-state-hover/focus-state-layer-opacity` instead of a hand-rolled 8% `color-mix`. Explicit `@media (prefers-reduced-motion: reduce)` block: `transform: none`, transitions to `0s`, state-layer transitions disabled; box-shadow elevation change retained (depth, not motion).
- **`scripts/lab-search-expansion.js`** (~7.5 KB) — refactored search expansion JS. Forbidden-ancestor bail-out (`bar.closest('.prose, .wp-block-post-content, .entry-content, [contenteditable]')`) at the top of the per-instance setup, before any DOM mutation — a `.search-bar` inside long-form content remains a plain HTML5 search input. Two-step Escape key policy (with text: clear and stay open; empty: blur + collapse) matching macOS / iOS search-field convention — addresses the GPT-flagged "검색어 입력 중 collapse 금지" semantic risk. Full ARIA combobox wiring: input gets `role="combobox"` + `aria-controls="<panel-id>"` + `aria-expanded` (kept in sync with `.is-search-active`) + `aria-autocomplete="list"`; each suggestion item gets `aria-selected="false"` + `tabindex="-1"` for roving focus. Keyboard arrow-nav between input and suggestions: ArrowDown from input → first option, Arrow keys within list, ArrowUp at first → returns to input, Home/End jump. `{ passive: true }` on pointerdown. Stable per-instance listbox id counter so multiple search-bars on one page get unique ARIA ids. `node -c` syntax check passes.
- **`lab-search-expansion-pattern.html`** (~9.6 KB) — demo page with five sections: allowlisted host (standard `.search-bar` markup from `components.css §10`); keyboard + Escape policy demonstration with explicit kbd-key instructions; forbidden-scope negative test (a `.search-bar` inside `.prose` + another inside `[contenteditable]` — both should remain plain inputs with no popup); reduced-motion path test; JS-disabled fallback notes.
- **`docs/SEARCH-EXPANSION-AUDIT.md`** (~10.6 KB) — per-module audit. Records 7 refinements (tokenization, state-layer compliance, reduced motion, `.prose` bail-out, Escape policy, ARIA wiring, keyboard nav), 2 deliberately-not-refined items (per-instance listener pattern + panel positioning), explicit theme-territory vs plugin-territory boundary (visual expansion + popup chrome + keyboard nav vs live search / federated query / suggestion data source / analytics), and the five-criterion promotion check (all PASS). Includes a Module-comparison-notes table contrasting ripple's delegated/stateless pattern with search's per-instance/stateful pattern — template for future module audits.

### Changed

- **`stylesheets/benchmark-interactions.css`** — `EXTRACTED: v3.3.4 → stylesheets/lab-search-expansion.css` marker inserted before `.search-bar` transition rules. Header `v3.3.x` extraction map updated: search expansion moved from "queued" to "EXTRACTED so far".
- **`scripts/benchmark-interactions.js`** — `EXTRACTED: v3.3.4 → scripts/lab-search-expansion.js` marker inserted before `function enableSearchBar()`. Header v3.3.x note updated.
- **`docs/BEER-CSS-INTAKE.md`** — Cross-reference table marks `lab-search-expansion.*` Done v3.3.4. Inventory table marks search expansion item as v3.3.4 Done. New change-log entry: "v3.3.4 — search-expansion extraction confirmed contract works outside ripple's scope. No rule changes." Documents the per-instance vs delegated listener nuance under §6 — the rule already permits per-instance when state is naturally per-instance; this is now explicit in `SEARCH-EXPANSION-AUDIT.md §Module-comparison notes`.

### Architectural decisions

- **Per-instance listeners vs ripple's single delegated listener** — search-bar state is naturally per-instance (panel id, ARIA wiring, Escape state machine). A document-level delegated listener would have to look up the host on every event. Intake §6 reads "one delegating listener per module" with the assumption that delegation is cleanly possible; this is the first module to clarify that per-instance is acceptable when state is per-instance. Documented in audit + intake change log.
- **Two-step Escape policy** — when GPT consultation flagged "검색어 입력 중 collapse 금지" as a search-specific semantic risk, the simplest reading would have been "swallow Escape if text is present". But that leaves no way for the user to dismiss the popup with the keyboard at all. The macOS / iOS convention (first Esc clears, second Esc dismisses) is well-established UX and preserves both invariants (no accidental query loss, full keyboard control). Implemented and documented as the canonical behavior.
- **Module-local custom properties + WCAG floor** — `44px` minimum tap target is a WCAG 2.5.5 Level AAA value, not a design rhythm value. M3 reference uses 48px. The audit explicitly documents why `44px` was chosen as the accessibility floor and why it lives as a module-local custom property (`--ax-search-suggestions-item-min-height`) rather than a `var(--space-*)` token: the existing space scale (4/8/16/24/32) does not include this value, and adding 44 to the global scale would muddy the rhythm system for one accessibility-driven outlier.
- **a11y combobox upgrade vs benchmark** — the benchmark had `role="listbox"` + `role="option"` on the popup but no `role="combobox"` / `aria-controls` / `aria-expanded` on the input, so screen readers announced the popup as a free-floating listbox. The lab module wires the full combobox/listbox/option triad. This is technically beyond "intake refinement" (it adds capability the benchmark never had) but is required by intake §1's semantic risk surfacing.

### Tools run

1. `python3 tools/generators/publish_styleguide.py` — publish surface now 18 files (was 17: +`lab-search-expansion.css`; JS and pattern HTML are correctly NOT copied, same lab-internal asymmetry).
2. `python3 tools/validators/validate_theme_pilot.py` — **1.000 / 1.000 PASS** across all four axes.
3. `node -c scripts/lab-search-expansion.js` — JavaScript syntax check passes.

### What this unblocks

- v3.3.5 anchored popover / split-button menu extraction is the next module under the contract. Popover is where keyboard / focus / accessibility risk is highest — having two prior extractions (one delegated/stateless, one per-instance/stateful) under our belt with the intake contract validated is the right precedent.
- The cross-module-listener-pattern nuance under §6 (delegated vs per-instance) is now documented.
- The Escape-key policy precedent is set for any future text-input-bearing interaction module.

### What this does NOT do

- Does not change `style-guide.html`, `style-guide-blocks.html`, `style-guide-prose.html`, or `components.css §10 Search bar`.
- Does not implement live suggestion filtering, autocomplete, or any query backend — that is plugin territory (see `SEARCH-EXPANSION-AUDIT.md §Theme territory vs Plugin territory`).
- Does not modify `publish_styleguide.py` or `validate_theme_pilot.py`.

### Statistics

| File | Status | Size |
|---|---|---|
| `stylesheets/lab-search-expansion.css` | new | 6,392 B |
| `scripts/lab-search-expansion.js` | new | 7,492 B |
| `lab-search-expansion-pattern.html` | new | 9,608 B |
| `docs/SEARCH-EXPANSION-AUDIT.md` | new | 10,634 B |
| `docs/BEER-CSS-INTAKE.md` | updated | +~12 lines (v3.3.4 change log + status flips) |
| `stylesheets/benchmark-interactions.css` | annotated | +~25 lines markers |
| `scripts/benchmark-interactions.js` | annotated | +~25 lines markers |
| `styleguide/stylesheets/lab-search-expansion.css` | new (orphan publish artifact) | 6,392 B |

---

## v3.3.3 — Beer CSS Intake Contract + Ripple Module Extraction (2026-05-13)

Second module extraction following v3.3.2 carousel, and the first one to encounter a procedural distinction: the Material You morph slider that v3.3.2 extracted was a single-reference, already-cleanly-tokenized pattern from `compare/Material You Slider.html`; Beer-CSS-derived items in `benchmark-interactions.{css,js}` are different — they were opportunistic GPT-Codex imports authored to test portability, not curated Axismundi code. So this release does two things at once: (1) establishes a cross-module **intake contract** that governs how *any* Beer-CSS-derived pattern is allowed into the project, and (2) extracts ripple as the first concrete module under that contract.

### Created (cross-module contract)

- **`docs/BEER-CSS-INTAKE.md`** (~9.6 KB) — cross-module scope contract for every interaction pattern that originated from the Beer CSS reference. Establishes nine intake rules: component-scope discipline (allow / forbid DOM-surface lists, with explicit `.prose` / `.wp-block-post-content` / `.entry-content` / `[contenteditable]` bail-out as a JS-side requirement); naming discipline (drop `ax-benchmark-` prefix on extraction); tokenization discipline (no hardcoded magic numbers; either `var(--md-sys-*)` token or a documented module-local custom property); reduced-motion discipline (`animation: none` over `animation-duration: 1ms`); JS-disabled fallback discipline; no global event-listener proliferation; state-layer awareness (complement, do not duplicate, `components.css §0`); federation portability check; and the v3.3.2 audit-trail policy carried forward as rule 9. Includes inventory of 8 Beer-CSS-derived items currently in benchmark and their target v3.3.x versions.

### Created (ripple module)

- **`stylesheets/lab-ripple.css`** (~7.2 KB) — refactored Axismundi-native ripple. Refinements vs benchmark original (`benchmark-interactions.css` L74–106): class prefix dropped (`.ax-ripple-host` / `.ax-ripple`), `opacity: 0.22` replaced with module-local `--ax-ripple-opacity: 0.16` declared at `:root` with M3 state-layer-opacity rationale comment, `780ms` replaced with `var(--md-sys-motion-curve-slow-spatial-duration)` (650ms) + matching cubic-bezier curve token, reduced-motion changed from `animation-duration: 1ms` to `animation: none` plus a static-tint-with-opacity-transition fallback path via a `.is-fading` class. `currentColor` background and physical (not logical) positioning are deliberately retained — documented in file header with M3 / RTL rationale.
- **`scripts/lab-ripple.js`** (~7.2 KB, self-contained IIFE) — refactored ripple JS. Refinements vs benchmark original (`benchmark-interactions.js` L27–64): explicit forbidden-ancestor bail-out (`.closest('.prose, .wp-block-post-content, .entry-content, [contenteditable]')`) added **before** the allowlist match, so a misplaced `.ax-button` inside a `.prose` container does not ripple; `pointerdown` listener marked `{ passive: true }`; idempotent host-class addition; capability-detected reduced-motion code path (`window.matchMedia('(prefers-reduced-motion: reduce)').matches`) which schedules `.is-fading` via `requestAnimationFrame` so the CSS transition fires correctly; `animationend` and `transitionend` both wired for cleanup (the original used only `animationend`, which would have left the ripple span in the DOM forever under reduced motion). `node -c` syntax check passes.
- **`lab-ripple-pattern.html`** (~11.7 KB) — demo page with three sections: (1) Allowlisted hosts — `.ax-button` (filled / tonal / outlined / text / disabled), `.ax-icon-button` (standard / filled / tonal), `.chip` (assist / filter active / filter inactive) — every entry in the JS `HOST_SELECTOR` allowlist is represented; (2) Forbidden-scope test cases — two visually distinct error-tinted boxes containing a valid `.ax-button` inside `.prose` and inside `[contenteditable]` respectively, with copy explaining that clicking these should NOT produce a ripple; (3) Reduced-motion path test — instructions for enabling reduced-motion at OS / DevTools level and what to expect.
- **`docs/RIPPLE-AUDIT.md`** (~10.5 KB) — first per-module audit under the intake contract. Records the extract-with-refinement decision (not reimplement-from-spec) with a 7-row refinement table, two explicit "deliberately not refined" notes (`currentColor` and physical positioning), and the five-criterion promotion check. Verdict: **PASS on all five criteria** — promotion to `components.css` is eligible after manual visual QA on the pattern page.

### Changed (audit-trail markers, no behavior change)

- `stylesheets/benchmark-interactions.css` — `EXTRACTED: v3.3.3 → stylesheets/lab-ripple.css` block-comment marker inserted before the `.ax-benchmark-ripple-host` rule. Header `v3.3.3 update` section: ripple moved from "queued for v3.3.3+ extraction" to "EXTRACTED so far". Existing rules kept verbatim — benchmark page still renders identically.
- `scripts/benchmark-interactions.js` — `EXTRACTED: v3.3.3 → scripts/lab-ripple.js` block-comment marker inserted before `function enableRipple()`. Header note updated to reflect v3.3.3 extraction. Bootstrap still calls `enableRipple()` from the IIFE — benchmark behavior unchanged.

### Architectural decisions

- **Extract-with-refinement, not reimplement-from-spec** — the benchmark ripple structure (CSS keyframe + JS DOM injection on pointerdown) is sound; the Beer-CSS-isms were concentrated in details (class prefixes, hardcoded numbers, missing scope bail-out) that could be corrected without rewriting the core mechanism. A full reimplementation would have been over-engineering. Documented in `RIPPLE-AUDIT.md §Approach taken`.
- **Module-local tokens preferred over forcing a wrong M3 token** — M3 does not define a `--md-sys-state-ripple-opacity`. Forcing `--md-sys-state-pressed-state-layer-opacity` (0.10) would have been a semantic mismatch (ripple is the *animated* press effect, not the static pressed state layer). The intake contract §3 explicitly allows module-local custom properties when an M3 token is not available, with documentation. `--ax-ripple-opacity: 0.16` is declared at `:root` with a comment that cites the dragged-state-layer-opacity as the semantic analog.
- **`.prose` bail-out is a contract, not a per-module decision** — the bail-out selector is normative for every Beer-CSS-derived module going forward (intake contract §1). Ripple is the first one to implement it; the same `.closest(...)` pattern will reappear in v3.3.4 search expansion, v3.3.5 popover, etc.

### Tools run

1. `python3 tools/generators/publish_styleguide.py` — publish surface now has 17 files (was 16: +`lab-ripple.css`; `lab-ripple.js` / `lab-ripple-pattern.html` / docs are correctly NOT copied, same lab-internal asymmetry as v3.3.2).
2. `python3 tools/validators/validate_theme_pilot.py` — **1.000 / 1.000 PASS** across all four axes.
3. `node -c scripts/lab-ripple.js` — JavaScript syntax check passes.

### What this unblocks

- v3.3.4 Search Bar Focus Expansion now has a contract to extract under (no further "should we make a contract?" deliberation).
- Manual visual QA on ripple in isolation, in light / dark theme, with and without reduced-motion, with and without `.prose` containers, is possible via `lab-ripple-pattern.html` without interference from any other benchmark interaction.
- Promotion of ripple to `components.css` is now a tractable next step rather than a contract-derivation problem (when the user is ready, the audit's verdict and refinement table show what merges cleanly).

### What this does NOT do

- Does not change `style-guide.html`, `style-guide-blocks.html`, or `style-guide-prose.html`.
- Does not promote ripple into `components.css`.
- Does not modify `compare/Beer CSS - …html` or any other frozen-reference asset.
- Does not modify `publish_styleguide.py` or `validate_theme_pilot.py`.

### Statistics

| File | Status | Size |
|---|---|---|
| `docs/BEER-CSS-INTAKE.md` | new (cross-module contract) | 9,592 B |
| `docs/RIPPLE-AUDIT.md` | new (per-module audit) | 10,473 B |
| `stylesheets/lab-ripple.css` | new | 7,156 B |
| `scripts/lab-ripple.js` | new | 7,209 B |
| `lab-ripple-pattern.html` | new | 11,720 B |
| `stylesheets/benchmark-interactions.css` | annotated | +~25 lines markers |
| `scripts/benchmark-interactions.js` | annotated | +~25 lines markers |
| `styleguide/stylesheets/lab-ripple.css` | new (orphan publish artifact) | 7,156 B |

---

## v3.3.2 — Carousel Lab Module Extraction (2026-05-13)

First module extraction following the v3.3.1 lab cleanup. The Material You morph slider/carousel — originally authored on the shared `benchmark-interactions.{css,js}` surface — is moved into a self-contained lab module so the remaining benchmark items (ripple, search expansion, popover, etc.) can be processed independently in later versions. Lineage: `compare/Material You Slider.html` (external reference, frozen workspace).

**Scope discipline**: this release is *extraction only*. The module is **not** promoted into `components.css §G3 Carousel` (which still holds the Phase 1B static visual-structure version). Promotion requires passing all five lab criteria from `docs/INTERACTION-AUDIT.md`; the carousel currently has a known blocker on `prefers-reduced-motion: reduce` and partial coverage on keyboard operability (see `docs/CAROUSEL-AUDIT.md §Current verdict`).

### Created

- `products/reference-implementations/axismundi-lab/stylesheets/lab-carousel.css` (8.7 KB, ~290 lines) — extracted from `benchmark-interactions.css` L271–520 with a v3.3.2 module header
- `products/reference-implementations/axismundi-lab/scripts/lab-carousel.js` (13.2 KB, ~352 lines) — self-contained IIFE with copied utilities (`qs`, `qsa`, `onReady`, `clamp`) + `enableMaterialYouSliders()` and all nested helpers + its own `onReady(...)` bootstrap. `node -c` syntax check passes.
- `products/reference-implementations/axismundi-lab/lab-carousel-pattern.html` (20.2 KB) — lab-internal demo page loading the core design-system stack + `lab-carousel.css` only. Includes a theme switcher row. Carousel demo content (four subsections: Multi-browse, Hero, Uncontained, and the interactive Material You morph slider) lifted verbatim from `style-guide-benchmark.html` L1416–1648.
- `products/reference-implementations/axismundi-lab/docs/CAROUSEL-AUDIT.md` — extraction record + module file inventory + Material You lineage notes + the five lab promotion criteria with current PASS/PARTIAL/NOT-VERIFIED verdict per criterion.
- `products/reference-implementations/axismundi-lab/docs/CAROUSEL-ONTOLOGY-CHECK.md` — records the core claim "Gallery is not Carousel; Gallery + horizontal layout context + progressive interaction layer can become a Carousel candidate" plus the theme-territory vs plugin-territory boundary, and sketches the conditional binding-map entry shape for when the module is eventually promoted.
- `products/reference-implementations/axismundi-lab/docs/CAROUSEL-VISUAL-QA.md` — 10-section manual QA checklist (render & layout × 3 layout variants, interactive demo, keyboard operability, reduced motion, JS-disabled fallback, `.prose` isolation, light/dark parity, Korean text). For the user to fill in by hand against the rendered pattern page.

### Changed (audit-trail markers, code unchanged)

- `stylesheets/benchmark-interactions.css` — top header gained a `v3.3.2 update` section that maps what was extracted (Material You morph slider/carousel → `lab-carousel.css`) and queues the remaining items for v3.3.3+ extraction (ripple → v3.3.3, search expansion → v3.3.4, popover → v3.3.5, tooltip/modal/date/time → v3.3.6). The original Material You section (L271–520) is preceded by an `EXTRACTED: v3.3.2 → stylesheets/lab-carousel.css` block comment. The rules themselves are kept verbatim so the benchmark page continues to render exactly as before.
- `scripts/benchmark-interactions.js` — same treatment: header gets the v3.3.2 extraction note, and the `clamp()` helper + `enableMaterialYouSliders()` are preceded by an `EXTRACTED: v3.3.2 → scripts/lab-carousel.js` block comment. The bootstrap at the bottom of the IIFE still calls `enableMaterialYouSliders()`, so `style-guide-benchmark.html` keeps working unchanged.

### Architectural decisions

- **Flat structure (option B) over modular folder (option A)** — discussed in the v3.3.2 planning round; option A (`lab/modules/carousel/`) would require updating `publish_styleguide.py` to be module-aware and reworking the asset-path rewriting. Option B keeps `publish_styleguide.py` unchanged and uses `lab-carousel.*` naming as the module marker. Modular folder structure is deferred to v3.4.x Lab Module Restructure once three or more modules have accumulated.
- **Benchmark deprecation policy** — extracted code is marked, not deleted. The benchmark-wide cleanup pass is a separate operation deferred to v3.4.x / v3.5.0. This preserves the audit trail of which lab module came from which benchmark section.
- **Publish-surface asymmetry, accepted** — `publish_styleguide.py`'s `*.css` glob picks up `lab-carousel.css` and copies it to `/styleguide/stylesheets/lab-carousel.css`, where it lives as an orphan (no HTML on the publish surface links to it). `lab-carousel.js`, `lab-carousel-pattern.html`, and the three `CAROUSEL-*.md` docs are *not* copied to the publish surface because the script only handles the explicitly listed scripts/HTML. This asymmetry is acceptable for v3.3.2 (the orphan CSS does no harm) and will be addressed in v3.4.x Lab Module Restructure together with the modular-folder migration.

### Tools run

1. `python3 tools/generators/publish_styleguide.py` — publish surface now has 16 files (was 15: +`lab-carousel.css`). All other publish-surface outputs unchanged.
2. `python3 tools/validators/validate_theme_pilot.py` — **1.000 / 1.000 PASS** (A schema 1.000, B theme 1.000, C css 1.000, D runtime 1.000). Pilot validator is unaffected because it validates `ontology-theme-pilot/`, not the styleguide.
3. `node -c scripts/lab-carousel.js` — JavaScript syntax check passes; IIFE structure verified balanced (1 open `(function () {`, 1 close `})();`).

### What this unblocks

- v3.3.3 Ripple module extraction can now follow the same procedure (the extraction methodology has been exercised end-to-end and documented).
- Visual QA on the carousel can proceed independently in `lab-carousel-pattern.html` without interference from the rest of the benchmark surface.
- The "Material You Slider" reference category in `compare/` is effectively closed — no further patterns to lift from that reference into Axismundi.

### What this does NOT do

- Does not change `style-guide.html`, `style-guide-blocks.html`, or `style-guide-prose.html` (the canonical published styleguide).
- Does not promote any code into `components.css` (the main M3 component layer).
- Does not touch the Phase 1B Date picker / Time picker structures restored by v3.3.1.
- Does not modify `publish_styleguide.py` or `validate_theme_pilot.py`.
- Does not modify `compare/` — that folder stays a frozen reference workspace.

### Statistics

| File | Status | Size |
|---|---|---|
| `stylesheets/lab-carousel.css` | new | 8,683 B |
| `scripts/lab-carousel.js` | new | 13,185 B |
| `lab-carousel-pattern.html` | new | 20,231 B |
| `docs/CAROUSEL-AUDIT.md` | new | 8,773 B |
| `docs/CAROUSEL-ONTOLOGY-CHECK.md` | new | 6,693 B |
| `docs/CAROUSEL-VISUAL-QA.md` | new | 6,868 B |
| `stylesheets/benchmark-interactions.css` | annotated | +~30 lines of audit markers |
| `scripts/benchmark-interactions.js` | annotated | +~30 lines of audit markers |
| `styleguide/stylesheets/lab-carousel.css` | new (orphan publish artifact) | 8,683 B |

---

## v3.3.1 — Lab Cleanup (2026-05-13)

> **Supplement — Visual QA (2026-05-13 later)**:
> Two render issues found during post-publish visual QA, both fixed and re-published.
>
> 1. **`pre > code` background collision** — `base.css` had `pre code { background-color: var(--md-sys-color-surface); }`. The comment immediately above states *"pre block alone provides the surface"*, so the inner `<code>` should inherit the parent `<pre>`'s `surface-container` tone, not paint its own `surface` rectangle on top. Result was a visible nested rectangle of a different tone inside every code block, including the `<pre class="sg-snippet"><code>` snippets in the styleguide itself (since `.sg-snippet` also uses `surface-container`). This was a misclassified hunk in the initial v3.3.1 cleanup — the lab change had been kept as a "visual improvement" but it directly contradicted the rule's own rationale. **Resolution**: rolled `pre code` back to `background-color: transparent` (the backup's value).
>
> 2. **Table stripes / header invisible inside `.sg-demo`** — `is-style-stripes` even rows and `.prose th` header band both used `surface-container-low`. The styleguide's `.sg-demo` container (in `style-guide-blocks.html`) also uses `surface-container-low` as its backdrop, so any table rendered inside a demo container showed neither header band nor zebra rhythm — the table read as completely flat. **Resolution**: moved BOTH the stripes rule (`blocks.css`) and the matching `.prose th` rule (`prose.css §11.4`) from `surface-container-low` to `surface-container-high`. This preserves the "header tone matches stripe tone" architectural intent (documented in the rules' own comments) while restoring visible contrast against the `.sg-demo` backdrop. `surface-container-high` was preferred over `surface-container-lowest` because `-lowest` (pure white in light theme) is *lighter* than the `surface-container-low` backdrop, which would invert the M3 "filled = more emphasis = darker tone" hierarchy and read visually wrong. The blocks.css rule's rationale comment was updated to reflect the new tone and to document the `.sg-demo` backdrop constraint so future contributors keep the two rules in sync. If the design intent shifts back toward a lower-density zebra (e.g., when stripes are viewed primarily in production prose context rather than the styleguide demo), both `blocks.css` and `prose.css §11.4` can move to `-low` or `-lowest` together — they are coupled by design.
>
> Re-publish: `/styleguide/` mirror regenerated. Validator: still 1.000 / 1.000 PASS.

Resolves `KNOWN-ISSUES.md` #1. Surgical removal of pre-lab-era contamination from the lab styleguide, with v3.2.x+ improvements preserved. Substrate is now clean and the pilot probe (v3.3.2) is unblocked.

### Approach

Compared the contaminated lab styleguide against a clean local backup (`Axismundi-phase-2B-beta-fix2b`, 2026-05-07 — pre-v3.2.2, pre-contamination) and the archived prototype on a per-hunk / per-section basis. Each change was classified as one of: (a) legitimate v3.2.x+ improvement to keep, (b) pre-lab-era contamination to roll back, or (c) accidentally lost v3.2.x improvement to restore.

### Rolled back to backup

- **`style-guide.html`** — text-field demo subsections (lab L2400–2540).
  Replaced lab's "Filled — 7 configurations" + "Outlined — 7 configurations" generic-English M3 reference matrices with the backup's three Korean-first subsections: "Prefix · Suffix · Counter · Native validation" (가격 ₩/KRW with HTML5 `pattern` + `:user-invalid`; 제목 with character counter), "Leading · Trailing icons" (검색, 이메일 with `type="email"`), and "Textarea" (메시지 작성, max 280자 with counter). Restores native-validation demo, character-counter demo, and Korean labels lost during the v3.3.0 prototype → lab copy.

- **`components.css`** — Time picker section (Chunk H4, lab L5616–5882).
  Replaced wholesale with backup's Phase 1B version. Recovers: `<input type="text" inputmode="numeric">` semantic markup for hour / minute fields; `<fieldset>` + `<input type="radio">` + `<label>` semantic AM/PM selector (with native keyboard arrow navigation); `.is-24h` modifier behavior; logical sizing (`inline-size` / `block-size`); token-based spacing (`var(--space-md)`, `var(--space-lg)`); `color-mix()` state-layer hover tint; `line-height: 0` inline-line-box alignment workaround with rationale comment.

- **`components.css` L1129** — `.text-field__input:has(~ .text-field__suffix) { text-align: end; }` rule.
  Restored. The Korean 가격/KRW form (and any future suffix-bearing input) depends on this for visual flush-with-suffix alignment.

### Restored from prototype (missing v3.2.1 work)

- **`prose.css` §12** — Icon font scope policy (Material Symbols).
  v3.2.1 added this section to the prototype's prose.css (federation portability + author UX + semantic boundary enforcement). When lab was constructed during v3.3.0, this block was not carried over. Lab's `prose.css` now includes the full §12 block (`.prose [class*="material-symbols"] { font-family: inherit !important; ... }`) plus the section listed in the file's top TOC. See `atlas/material/icon-font-scope-policy.md` for the full doctrine.

### Preserved as legitimate v3.2.x+ / Phase 2A improvements

- `style-guide.html` hunks 1–4: `left: 50%` → `inset-inline-start: 50%` (logical CSS for RTL), `aria-pressed` → `role="radio"` + `aria-checked` on theme switcher (proper ARIA semantics for single-select group).
- `style-guide.html` hunks 8–9: slider `style="--_value: N%"` initial fill (avoids FOUC before JS hydration).
- `style-guide-prose.html`: inline TOC scroll-spy `<script>` block removed (script body migrated to `scripts/theme.js §5` as Phase 2B planned; verified by inspecting both files).
- `base.css` §6.7 Skip link (WCAG 2.4.1 Bypass Blocks, "메인 콘텐츠로 건너뛰기") + §6.8 `.visually-hidden` utility.
- `base.css` `pre code` + native `<select>` background-color: `transparent` → `var(--md-sys-color-surface)` and select 1px border (Phase 2A native-select work).
- `blocks.css` table `is-style-vertical-borders` selector restructure (inter-cell separators → full-grid pattern with `:last-child` reset, per M3 spec reference imagery).
- `prose.css` §2.1 / §2.2 paragraph + block-punctuation rhythm refactor; fixes the silent `.prose ul/ol { margin-block: 0 }` cascade bug.
- `components.css`: 15 hunks of logical-CSS-property migration (`top`/`left`/`right` → `inset-block-start` / `inset-inline-*`; `border-top-{left,right}-radius` → `border-start-{start,end}-radius`; gradient `to right` → `to inline-end`; animation keyframes).
- `components.css`: focus-indicator `3px` → `2px` (per M3 spec).
- `components.css`: text-field error / disabled rules moved out and re-added with `-webkit-text-fill-color` for iOS Safari rendering parity.
- `components.css` L3881: slider color-token doctrine comment block (M3 Slider spec read 2026-05-08).

### Tools run

1. `python3 tools/generators/publish_styleguide.py` — regenerated `/styleguide/` mirror from cleaned lab (8 stylesheets + 3 HTML pages + script + README, 15 files total).
2. `python3 tools/validators/validate_theme_pilot.py` — **1.000 / 1.000 PASS** across all four axes (A schema 1.000, B theme 1.000, C css 1.000, D runtime 1.000). The validator validates `ontology-theme-pilot/`, not the styleguide, so a clean styleguide cleanup does not perturb pilot validation. Result: confirms the lab cleanup didn't accidentally touch anything binding-adjacent.

### Files changed

```
KNOWN-ISSUES.md                                                        marked #1 Resolved + appended Resolution notes (v3.3.1)
ROADMAP.md                                                             v3.3.1 marked done; v3.3.2 promoted to (NEXT)
CHANGELOG.md                                                           this entry
products/reference-implementations/axismundi-lab/style-guide.html      Korean text-field subsections restored
products/reference-implementations/axismundi-lab/stylesheets/components.css   Time picker rolled back to Phase 1B + suffix-right-align rule restored
products/reference-implementations/axismundi-lab/stylesheets/prose.css        §12 icon scope policy restored from prototype; TOC updated
styleguide/                                                            republished mirror (15 files)
```

### Statistics

| File | Before (lab v3.3.0) | After (lab v3.3.1) | Δ |
|---|---|---|---|
| `style-guide.html` | 193,252 B | 189,582 B | −3,670 |
| `stylesheets/components.css` | 203,530 B | 206,240 B | +2,710 |
| `stylesheets/prose.css` | 14,636 B | 16,467 B | +1,831 |

### Verification

Pilot validation: **1.000** (PASS). `/styleguide/` mirror regenerated successfully (15 files). All Phase 1B Time picker markers restored (input variant + `<fieldset>`+`<radio>` AM/PM + `.is-24h` + token-based spacing + `inline-size`/`block-size`). All Korean labels restored in published `index.html` (가격, 제목, 숫자만 입력해 주세요, 검색어 입력, 이메일, 로그인에 사용할 이메일 주소, 메시지 작성, 최대 280자). §12 icon scope present in published `prose.css`. Suffix right-align rule present in published `components.css`.

---

## v3.3.0 — Lab Promotion + Legacy Split (2026-05-13)

> **Supplement (2026-05-13 later)**:
> - Phase 8 KB closure report (`cowork-phase8-kb-build-closure.md`) added to archive
> - Constitution Article 1 amended from 4-layer to 6-layer architecture (canonical A–F mapping). Preamble adds authorship statement
> - PROJECT-REPORT.md supplemented with Phase 8 KB precision detail. Phase 9 anti-patterns articulated
> - **AUTHORSHIP.md created** at root — primary author (KIM Ji-woon) named, LLM-as-amplification-tools clarified, decision signature mapped (Tier 1 universal / Tier 2 LLM-nudged / Tier 3 pure user originations). Meta-doctrine section added linking to KB operating rules.
> - README.md preamble updated with author + version
> - **Pre-monorepo ultrareview specific findings retired** — `gpt-phase-2b-gamma-3-ultrareview.md` removed; methodology preserved as `SUPERSEDED-ULTRAREVIEW.md` for future re-audit triggers
> - **Cowork KB operating rules archived** — 11 numbered chunk-authoring rules + project vision + English-only + generic-vs-project-layer rules preserved at `products/_archive/_pre-monorepo-reports/cowork-kb-operating-rules/`. Subfolder README maps each rule to its current monorepo manifestation. These remain *live doctrine* for future KB extension; not retired with v3.3.0.

Structural shift: lab promoted to active visual authority, prototype demoted to legacy archive, assets relocated to design system layer.

### Authority migration

```
Before v3.3.0:                          After v3.3.0:
  prototype = visual authority            prototype = legacy archive
  lab       = experimental surface        lab       = active visual authority
  assets    = prototype-local             assets    = design-system-shared
```

### Operations performed

1. **Asset relocation** — fonts + icons moved from prototype to `core/design-systems/material3/assets/`. Shared by all consumers (lab, future distributable theme, publish surface).
2. **prototype → archive** — `axismundi-prototype/` moved to `products/_archive/axismundi-prototype/`. `_LEGACY.md` placed inside documenting the demotion reason (social-CMS-frame contamination).
3. **lab as active authority** — `style-guide-blocks.html` and `style-guide-prose.html` copied from prototype into lab. lab is now self-contained with all 3 style guide pages (`style-guide.html`, `style-guide-blocks.html`, `style-guide-prose.html`, plus the experimental `typography-axis.html` and `style-guide-benchmark.html`).
4. **Publish source change** — `publish_styleguide.py` now mirrors from `axismundi-lab/` instead of `axismundi-prototype/`. The script also rewrites font asset paths from lab-relative to publish-relative depth.
5. **`/styleguide/` re-published** — root publish surface regenerated as a mirror of lab.
6. **Reference updates** — 3 MD/HTML files had references to `axismundi-prototype` updated to `axismundi-lab` (4 if counting historical changelog mentions, which are preserved).
7. **CONSTITUTION Article 12 amended** — publish surface authority migration documented as Rule 5.

### Created

- `core/design-systems/material3/assets/` (with fonts/ + icons/ + README.md explaining consumer paths and rationale for the move)
- `products/_archive/` (new layer for demoted reference implementations)
- `products/_archive/axismundi-prototype/_LEGACY.md` (demotion notice)

### Changed

- `tools/generators/publish_styleguide.py` — source path; font path rewriting on copy
- `CONSTITUTION.md` Article 12 — Rule 5 added; current publishing surface path updated
- `ROADMAP.md` — v3.3.0 done, v3.3.1 (or v3.4.0) "Pilot Block Theme Probe" added as next

### Why pilot block theme is NOT in this release

Per audit (and GPT consultation), creating a pilot block theme now would
freeze an unstable lab/prototype mixture into a theme structure. The pilot
must consume *validated* styleguide + ontology as input. v3.3.0 establishes
that validation surface (lab); v3.3.1 will construct the pilot from it.

Order:
```
1. prototype → legacy             ✓ v3.3.0
2. lab active                     ✓ v3.3.0
3. styleguide × 3 in lab          ✓ v3.3.0
4. lab interaction/typography QA  ← next phase, ongoing
5. styleguide + ontology → pilot  ← v3.3.1 or v3.4.0
```

### Validation

Pilot validation script passes 1.000 / 1.000 from the new path (lab).
`publish_styleguide.py` produces 15 files in `/styleguide/` mirror.

### Statistics

| Layer | Before | After | Δ |
|---|---|---|---|
| `core/` | 27 files, 636K | **63 files, ~13M** | +36 files (assets moved here) |
| `products/_archive/` | (new) | **77 files** | new (prototype moved here) |
| `products/reference-implementations/` | 4 dirs | **3 dirs (active)** | prototype out |
| `products/reference-implementations/axismundi-lab/` | 21 entries | **23 entries** | +blocks.html, +prose.html |

---

## v3.2.3 — Font Coverage Fix (2026-05-13)

Corrects a strategic error in v3.2.0: Roboto fonts were Latin-subset, which dropped characters real Korean pages need (₩, ←→, ≈≠≤≥, ★, Greek, Cyrillic, additional currency). Re-converts the Roboto family to full coverage and clarifies the role split with Noto.

### Strategy refinement

```
Before (v3.2.0):
  Roboto Flex     → Latin subset (231 glyphs)
  Noto Sans KR    → Korean subset
  Result          → ₩, ←→, ≈≠≤≥ missing on Korean pages

After (v3.2.3):
  Roboto Flex/Serif/Mono  → Full no-subset (826/919/876 glyphs)
                            Non-CJK base layer: Latin + Greek + Cyrillic +
                            currency + arrows + math operators + punctuation
  Noto Sans/Serif KR      → Korean subset (Hangul + Jamo)
                            CJK fallback layer via unicode-range
```

Future expansion to Noto Sans JP / SC is a clean *user-managed* addition via WordPress's official Google Fonts integration (Font Library, 6.5+) or upload — Axismundi does not need to ship every CJK font; Roboto remains in place as the non-CJK base.

### Changed

- **Re-converted Roboto fonts** (TTF → WOFF2 full coverage, no glyph subset):
  - Roboto Flex: 324KB → **745KB** (Latin subset → full 826 glyphs, all 13 axes preserved)
  - Roboto Serif: 703KB → **1.5MB** (full 919 glyphs)
  - Roboto Mono: 38KB+41KB → **101KB+109KB** (full 876 glyphs, roman + italic)
- **Filename change** — dropped `-latin` suffix (no longer subset):
  - `axismundi-roboto-flex-latin.woff2` → `axismundi-roboto-flex.woff2`
  - `axismundi-roboto-serif-latin.woff2` → `axismundi-roboto-serif.woff2`
  - `axismundi-roboto-mono-latin.woff2` → `axismundi-roboto-mono.woff2`
- **`fonts.css` updated**:
  - Roboto declarations: `unicode-range` removed (full coverage = base layer)
  - Noto declarations: `unicode-range` kept (scope to Korean glyphs only)
  - Documentation comment explains strategy + future CJK expansion
- **`assets/fonts/README.md` rewritten** to document the new strategy and expansion path
- **`LICENSE-MATRIX.md`** filename references updated
- **`lab/stylesheets/fonts.css`** synchronized (refers to prototype assets via relative path)

### Unchanged

- Noto Sans KR / Noto Serif KR — Korean subset retained (Hangul Syllables = 11,172 glyphs alone; full font is 23,174 glyphs, subset is appropriate)
- Material Symbols Rounded — full glyph table (ligatures need it; never subset)
- All variable font axes preserved across all conversions

### Size impact

- Roboto family total: 1.1MB → **2.4MB** (+1.3MB)
- Full font directory: 4.5MB → **5.7MB**
- This is the cost of correctly covering ₩, currency, arrows, Greek, Cyrillic, math operators. Acceptable for Korean-first microblog target.

### Source.txt provenance

Each Roboto folder's `source.txt` updated to reflect "no glyph subset" processing. Conversion date recorded.

### Validation

Pilot still passes 1.000 / 1.000. fonts.css update is local to prototype; ontology layer unaffected.

---

## v3.2.2 — Interaction Lab Audit (2026-05-13)

Establishes the lab promotion pipeline. Renames the former benchmark surface to `axismundi-lab/` and audits its 9 components for promotion to the prototype.

### Renamed

- **`axismundi-benchmark/` → `axismundi-lab/`** — reflects actual role. Benchmark was the user's term for what is structurally a *lab* — visual QA, promotion candidates, and design exploration. All references in scripts (0), docs (4: LICENSE-MATRIX, NOTICE, README, CHANGELOG) updated automatically.

### Added

- **`axismundi-lab/README.md`** — lab role definition, promotion criteria (5-point checklist), relationship to prototype/ontology-pilot.
- **`axismundi-lab/docs/INTERACTION-AUDIT.md`** — first audit document. Documents 9 components: 5 promoted, 4 held with blockers. Includes audit methodology and future audit cadence.
- **`axismundi-lab/typography-axis.html`** — variable font axis explorer. Drag sliders for opsz, wght, wdth, slnt, GRAD; observe live rendering on Roboto Flex (text) + Material Symbols (icons). Demonstrates **GRAD axis sync** between text and icons — the M3 spec's "match grade for harmonious visual effect", made interactive.
- **`.nojekyll`** (root) — GitHub Pages optimization. Bypasses Jekyll so WOFF2 fonts and underscore folders serve predictably.

### Audit results (lab → prototype promotion)

**Promoted candidates (documented; code merge deferred to user)**:
- Ripple (CSS keyframes + JS attach)
- Anchored popover menu
- Search bar focus expansion
- Slider value chip
- Material You morph slider/carousel

**Held in lab (with documented blockers)**:
- Date picker — design unfinished, locale formatting incomplete, mobile fallback unclear
- Time picker — same blockers as date picker
- Tooltip — need not established for current theme uses
- Modal benchmark variants — defer to native `<dialog>` integration

### Changed (refinements during audit)

- **Slider inactive track color** in `prototype/components.css`: `on-secondary-container` → `secondary-container`. M3 spec defines inactive track as a *container surface* (lower emphasis), not foreground. Audit benchmark version was correct; prototype was over-emphasized.
- **`prototype/prose.css` header comment**: documented why Korean/CJK rules are NOT duplicated in prose.css — body-level `word-break: keep-all` + `overflow-wrap: anywhere` + `line-height: 1.6` cascades through. Prevents future contributors from "fixing" a non-bug.
- **`lab/benchmark-interactions.css` header**: now declares lab status + promotion decision (5 promoted, 4 held) directly in the comment block.

### Architectural decisions

- **Korean/Latin typography** — *audited and confirmed correct*. Body-level inheritance is the right design; no changes needed. Documented to prevent future erosion.
- **Promotion criteria** formalized as a 5-point checklist in lab/README.md.
- **Audit cadence** — ad-hoc, triggered by promotion candidates. Each audit produces a numbered file (`INTERACTION-AUDIT-vN.md`).

### Future (deferred to user / v3.2.3)

- Actual CSS code merge for the 5 promoted components into `prototype/components.css` (audit documents the decision; merge is a careful step that user should drive)
- Actual JS code merge into `prototype/scripts/theme.js` or new `interactions.js`
- `lab/forms-date-time.html` (date/time picker iteration page)
- `lab/icon-font-swap.html` (inline SVG → Material Symbols visual QA)
- Styleguide page demonstrating GRAD sync (live demo, not lab tool)

### Validation

Pilot validation still passes 1.000 / 1.000.

---

## v3.2.1 — Font Runtime Integration (2026-05-13)

Wires the v3.2.0 self-hosted font assets into the runtime CSS layer.

### Added

- **`stylesheets/fonts.css`** — @font-face declarations for all 4 web-loaded fonts:
  - Roboto Flex (Latin, 13 axes preserved)
  - Noto Sans KR (Korean, with unicode-range fallback for Latin → Roboto Flex)
  - Roboto Mono (Latin, separate roman + italic)
  - Material Symbols Rounded (icon font, font-display: block to avoid ligature flash)
  - Optional fonts (Roboto Serif, Noto Serif KR, additional Material Symbols styles) intentionally NOT declared here
- **`stylesheets/icons.css`** — Material Symbols base styling:
  - `.material-symbols-rounded` class with axis defaults
  - CSS custom properties for axis control (`--md-icon-fill`, `--md-icon-weight`, `--md-icon-opsz`)
  - GRAD axis sync with Roboto Flex via shared `--md-grade`
  - Dark mode grade adjustment (-25 per M3 spec)
  - Expressive state: FILL transition on hover/focus/aria-pressed (M3 motion)
  - Integration with existing `.ax-icon-button` from components.css
- **`tokens.css` §1.4`** — `--md-grade` declared at `:root` level (shared between text and icons)
- **`stylesheets/prose.css` §12`** — icon scope enforcement: `.prose [class*="material-symbols"]` reverts `font-family: inherit !important` to break ligatures in content
- **`patterns/icon-button-search.html`** — first static icon-button pattern. Proves theme-only rendering works (no plugin needed).

### Architectural decisions

- **Theme ships only Material Symbols Rounded** (~5.3MB). Outlined and Sharp are NOT shipped with the theme — they belong to the future `axismundi-icons` plugin. Sharp may be omitted entirely (Korean microblog target unlikely to need geometric/technical style).
- **Optional fonts policy**: Roboto Serif and Noto Serif KR stay in `assets/fonts/` (already shipped) but are NOT in fonts.css @font-face. A future prose-serif style variation or extension would declare them. Default theme = sans only.
- **`font-display: block` for icons** vs `swap` for text: text gets fallback during load (FOUT), icons stay invisible during load (no "home" word flashing before icon appears).
- **Progressive enhancement architecture**:
  - **Theme** = renderer: provides @font-face, base classes, icon-button pattern. Works standalone.
  - **Plugin** (future): provides picker UI, additional styles, axis controls, block inspector. Optional.
  - Same DOM markup works in both — plugin doesn't replace theme, just adds editor UX.

### Files changed in axismundi-prototype/

```
stylesheets/
├── fonts.css          NEW (60 lines)
├── icons.css          NEW (130 lines)
├── tokens.css         +18 lines (--md-grade in §1.4)
└── prose.css          +35 lines (§12 icon scope enforcement)

patterns/
└── icon-button-search.html  NEW (concept proof)
```

### Plugin scope confirmed (deferred to v3.3+)

`products/distributables/plugins/axismundi-icons/` (planned) will provide:
- Material Symbols Outlined (+optionally Sharp) — additional WOFF2 + @font-face
- Icon picker UI in block inspector sidebar
- Block variation extending `core/button` with icon attribute
- Axis controls (FILL, GRAD, opsz, wght) per icon instance
- Icon search dataset (Material Symbols catalog metadata)

### What still hasn't happened

- Theme `functions.php` still doesn't enqueue fonts.css / icons.css (the actual `wp_enqueue_style` PHP). Static prototype renders these directly via HTML `<link>` tags. The PHP integration will happen at the distributable theme creation stage (v3.3+).
- `<link rel="preload">` directives in `parts/header.html` for critical fonts (v3.2.2).
- Styleguide page demonstrating icon usage (v3.2.2).

### Validation

Pilot still passes 1.000 / 1.000.

---

## v3.2.0 — License, Font, Icon Foundation (2026-05-13)

Asset foundation work: license matrix + self-hosted variable fonts + Material Symbols with explicit scope policy.

### Added

- **`LICENSE-MATRIX.md`** (root) — per-asset license declarations. Public release license intent: GPL-3.0-or-later. Per-asset breakdown: Apache 2.0 (Material Symbols), OFL 1.1 (5 fonts), CC-BY-4.0 (proposed for data/docs), GPL-3.0-or-later (theme code).
- **`NOTICE.md`** (root) — Apache 2.0 attribution (Material Symbols), OFL 1.1 attributions (all fonts), inspirational acknowledgments (Beer CSS, M3 spec, WordPress).
- **Self-hosted variable fonts** in `axismundi-prototype/assets/fonts/`:
  - Roboto Flex (Latin subset, 13 axes preserved): 324KB
  - Roboto Serif (Latin subset): 703KB
  - Roboto Mono (Latin + Italic): 38+41KB
  - Noto Sans KR (Korean+Latin subset): 1.25MB
  - Noto Serif KR (Korean+Latin subset): 2.16MB
  - All with `OFL.txt` + `source.txt` per OFL 1.1 §4
- **Material Symbols icon fonts** in `axismundi-prototype/assets/icons/`:
  - Outlined (3.9MB), Rounded (5.3MB), Sharp (3.4MB)
  - Variable fonts with full 4-axis (FILL, GRAD, opsz, wght)
  - Full glyph table preserved (no subset — icon font ligatures require it)
  - All with `LICENSE.txt` + `source.txt`
- **`atlas/material/icon-font-scope-policy.md`** — new atlas rule defining where Material Symbols can/cannot be used. Three failure modes documented: federation portability, author UX, semantic boundary. Critical doctrine for icon font usage in any federated WordPress theme.
- **`assets/fonts/README.md`** — @font-face examples, preload strategy, GRAD axis sync between text/icons (M3 spec implementation).
- **`assets/icons/README.md`** — full scope policy, accessibility patterns, `notranslate` requirement, axis usage.

### Changed

- **`axismundi-v1.0.0-rc1/` → `axismundi-prototype/`** — explicit rename reflects the static-prototype nature. The "RC" name was misleading; "prototype" makes the role clear. Future actual RC will start at `products/distributables/themes/axismundi/`. Script paths and index.html links updated.
- **`index.html`** — link label "RC theme (v1.0.0-rc1)" → "Prototype theme (static)".

### Architectural decisions

- **Icon font scoping policy** (3 reasons):
  1. ActivityPub federation strips CSS classes → ligatures break in federated timelines
  2. Common English words trigger ligatures → author UX disaster
  3. Theme chrome vs content boundary violation
- **Icon fonts forbidden in `.prose` + `post_content`**, allowed in FSE template parts and theme chrome only. Enforced via CSS in prose.css.
- **GRAD axis sync** between Roboto Flex (text) and Material Symbols (icons) via single CSS variable `--md-grade`. M3 spec recommends this for visual harmony; Axismundi appears to be the first WP theme implementation.
- **Font modifications under OFL 1.1**: TTF→WOFF2 format conversion + unicode subset. Reserved Font Names preserved in CSS (`'Roboto Flex'`, etc.). Modified files prefixed with `axismundi-` to indicate format-only alteration.
- **Material Symbols NOT subset**: icon font ligatures depend on full glyph table. Format conversion only (TTF→WOFF2).

### Future (deferred to v3.2.x patches)

- `tokens.css` @font-face block update with new self-hosted paths
- `prose.css` enforcement CSS for icon scope policy
- Theme `<head>` preload directives in template parts
- Icon picker plugin (v3.3+, `products/distributables/plugins/axismundi-icon-picker/`)

### Validation

Pilot validation script still passes 1.000 / 1.000 from renamed path.

### Asset compression summary

- Input TTF total: ~74 MB
- Output WOFF2 total: 17 MB (22% of input, with all axes preserved)
- Fonts only: 4.5 MB (Korean fonts dominate)
- Icons only: 12.6 MB (full glyph preservation costs)

---

## v3.1.0 — RC Integration + Publishing Surface (2026-05-12)

Two big additions: the real Axismundi RC theme enters the monorepo, and the publishing surface concept is formalized.

### Added

- **`products/reference-implementations/axismundi-v1.0.0-rc1/`** — the actual user-authored Axismundi RC (Phase 1B complete, 33 components, 5 stylesheets, 16 templates, 3 style guides, GPL-3.0). This is the **source authority** for the Axismundi theme.
- **`products/reference-implementations/axismundi-lab/`** — Beer CSS-inspired interaction layer experiment (ripple, carousel, popover). Kept separate from the main RC; may migrate into the RC's components.css if approved.
- **`atlas/material/`** — Material Design 3 rule-based knowledge (text-fields-spec + text-fields-impl). Atlas is no longer wordpress-only — it's now multi-domain. Follows the same 6-slot DSL as `atlas/wordpress/`.
- **`/styleguide/`** (publishing surface) — auto-generated mirror of the RC's style guide HTML + stylesheets. **Do not edit directly.** Regenerate via `tools/generators/publish_styleguide.py`.
- **`/index.html`** (publishing surface) — project landing page for GitHub Pages. Uses tokens.css for visual consistency with the rest of the project.
- **`tools/generators/publish_styleguide.py`** — generator that publishes RC style guides to root mirror. Rewrites cross-references between style guides (`style-guide-blocks.html` → `blocks.html`).
- **`CONSTITUTION.md` Article 12** — Publishing surfaces are mirrors, not authorities. Defines `/styleguide/` and `/index.html` as derived artifacts; explicit generator rule; source-edit-only policy.
- **`axismundi-v1.0.0-rc1/docs/ROADMAP.md`** — the user-authored product roadmap (Phase 9 MVP build), preserved separately from the monorepo `ROADMAP.md`.

### Changed

- **`products/reference-implementations/theme-pilot/` → `ontology-theme-pilot/`** — explicit naming distinguishes the ontology validation pilot from the real RC. Both keep their roles: pilot validates the binding map can emit code; RC is the actual ship target.
- **`ROADMAP.md`** — documents the two-roadmap structure (monorepo vs product). v3.1 was originally HCT plugin; moved to v3.2 to make room for v3.1.0 RC integration.
- **`corpus/source/dev-handbook/` removed** — raw scrape no longer stored in monorepo. Now consistent with WP/GB upstream handling (external reference via MANIFEST.md). Patches are anchor-based (`find`/`replace`/`reason`) so reproducibility is preserved without the raw. Saved ~6.7MB; corpus layer now 6.2MB (was 13MB).

### Architecture impact

`atlas/material/` is a quiet but important shift. Previously the atlas layer implied "atlas/wordpress/" as the only domain. Now atlas is a multi-domain knowledge layer:

```
atlas/
├── wordpress/   (113 rules, 11 bounded contexts)
└── material/    (2 rules, growing — text-fields-spec, text-fields-impl)
```

Future: atlas/ may grow `activitypub/`, `<other-design-system>/` etc., matching the core/ layer's multi-domain structure.

### Validation

`tools/validators/validate_theme_pilot.py` still passes 1.000 / 1.000 from the new `ontology-theme-pilot/` path. Binding ontology integrity preserved.

### What this is NOT

- Not a release of `axismundi-v1.0.0-rc1` — still in `reference-implementations/`, not `distributables/`. Promotion to distributable happens when the RC's docs/ROADMAP Phase 9 work completes.
- Not a switch from theme-pilot to RC for binding work — both reference implementations stay. They demonstrate different things.

---

## v3.0.1 — Structural Hardening (2026-05-12)

Post-normalization governance + structure polish. No content changes; only architectural surface.

### Added

- **`core/design-systems/material3/DESIGN-DOCTRINE.md`** — Why Axismundi keeps `tokens.css` as the design SoT instead of `theme.json`. 6 doctrines + 8 locked decisions from v1→v4 iteration history. Defends the architecture against future "why don't we just put hex in theme.json?" pressure.
- **`CONSTITUTION.md` Article 11** — Design Doctrine delegation rule. Layer constitution stays general; design-system-specific rationale lives in each design system's own DESIGN-DOCTRINE.md.
- **`bindings/_spec/binding-schema.md`** — D-layer grammar. Required fields per binding type, confidence taxonomy with source minimums, binding_pattern enumeration, lifecycle states, gap declaration requirements. Makes the binding layer self-validating.
- **`products/reference-implementations/`** vs **`products/distributables/`** — Two-track product split. Reference implementations are validation targets (`theme-pilot`); distributables are semver-versioned products with stable contracts. Prevents pilots and shipping products from polluting each other.
- **`core/wordpress/projections/`** — Reserved for v3.1+. Focused subset views over the full ontology (block-supports projection, theme-json projection, rest-routes projection). Derived from `ontology.jsonld`, not authored.
- **`ROADMAP.md`** — v3.0.x → v4.0 trajectory. HCT plugin (v3.1) → ActivityPub alpha (v3.2) → second design system (v3.3) → public release (v4.0).

### Changed

- **`products/theme-pilot/` → `products/reference-implementations/theme-pilot/`** — moved to reflect its role as validation target, not distributable. Path references in `tools/validators/` and `tools/generators/` updated.

### Why

Per GPT analysis: *"Material plugin / ActivityPub binding / additional themes will cost much more to add if structure remains ambiguous. v3 normalization is cheapest at this moment."* v3.0.0 did the normalization; v3.0.1 hardens it before content expansion begins in v3.1.

### Validation

Theme pilot validation still passes 1.000 / 1.000 from new location (`products/reference-implementations/theme-pilot/`).

---

## v3.0.0 — Monorepo Normalization (2026-05-12)

**Structural release. No content changes; only path reorganization + naming clarity.**

### Migration summary

| Before | After | Reason |
|---|---|---|
| `refine_work/` | (split — see below) | "refine" is a process, not a layer |
| `knowledge_check/knowledge/wordpress/` | `atlas/wordpress/` | "knowledge" is too vague; atlas reflects topological structure |
| `_meta/v2_0/` (ontology core) | `core/wordpress/` | Layer C separated by platform vs. design |
| `_meta/v2_1/` (M3 binding) | split: `core/design-systems/material3/` + `bindings/wordpress-material3/` | Design system is interchangeable; binding is a separate layer |
| `v2_1_axismundi_input/` (M3 docs) | `core/design-systems/material3/specs/` + `runtime/` + `bindings/.../_source/` | Three different roles: spec, runtime, binding source |
| `axismundi-theme-pilot-v0.1/` | `products/theme-pilot/` | Products are deliverables, separately layered |
| `refine_work/scripts/` | `tools/refine/` + `tools/builders/` + `tools/generators/` + `tools/validators/` | Tools are not a layer; classify by what they operate on |

### What's new (structural)

- **`CONSTITUTION.md`**: Layer principles, naming rules, failure modes
- **`core/design-systems/material3/`**: M3 isolated as a replaceable design system
- **`core/federation/activitypub/`**: Placeholder for future federation ontology
- **`bindings/`**: D layer made explicit; bindings live separately from core
- **Path rewrites**: All scripts now use relative-to-monorepo paths (no `/home/claude/...` hardcoding)

### Migrated assets

- 35 layer assets (ontologies, specs, runtime CSS, pilots, atlas, corpus)
- 31 tool scripts (refine + builders + generators + validators + manifests)
- All atlas rules (135 markdown files across 11 bounded contexts)
- All refined corpus (~630 markdown files from v1.1a)

### What did NOT change

- **Content of any layer**: every ontology entity, every binding rule, every atlas rule preserved verbatim
- **WP 6.9.4 + GB 23.1.1 baseline pin**: unchanged
- **Validation scores**: pilot still passes 1.000 / 1.000
- **v2.1a-P0.5 architectural decision**: tokens.css remains SoT, theme.json remains var()-based

### Breaking changes

None for content. **Path-breaking** for anyone using the old structure:

```python
# Before
"_meta/v2_0/ontology_core_draft_v0_2.jsonld"
# After
"core/wordpress/ontology.jsonld"

# Before
"_meta/v2_1/wp_to_m3_binding_map.json"
# After
"bindings/wordpress-material3/binding_map.json"
```

Tool scripts have been updated. External references (if any) need to be updated manually.

### Why now

GPT analysis: *"Material plugin / ActivityPub binding / additional themes will cost much more to add if structure remains ambiguous. v3 normalization is cheapest at this moment."* Confirmed correct.

---

## v2.1a-P0.5 — Binding Legitimacy Audit + Pilot Theme (2026-05-12)

Pilot block theme scaffold + 4-axis validation script. Architectural decision: tokens.css is SoT; theme.json uses var() chain; inspector GUI limits are intentional, to be replaced by future plugins.

Score: 1.000 / 1.000 PASS (Schema / Theme / CSS / Runtime axes).

## v2.1a-P0 — Material Binding Layer (2026-05-12)

- M3 token ontology v0.1 (273 entities, 8 families)
- WordPress↔Material binding map (6 token bindings + 32 component bindings)
- Block-component binding rules (48 rules from BLOCK-COMPONENT-MAP, 6-bucket → ontology grammar)

## v2.0 — WordPress Ontology Core (2026-05-12)

- v0.2 unified: Block axis (84 entities) + Theme axis (22) + Bridges (8) = 114 entities
- 5-way validation: corpus + docs + schema + WP runtime + atlas
- Atlas integration: 39/113 rules referenced, theme-config 100% covered

## v1.1a — Corpus Refinement (2026-05-11)

- Dev handbook sanitization: 38 patches (12 link + 9 lang_fix + 17 markdown_list)
- 630 markdown files, 5 validation checks passing, 0 structural anomalies
