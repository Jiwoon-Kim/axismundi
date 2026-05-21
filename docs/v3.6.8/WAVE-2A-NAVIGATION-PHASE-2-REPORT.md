# v3.6.8 Wave 2A Navigation Phase 2 Report

Status: Phase 2 complete; ready for Phase 2 review.

## Verdict

Route B - Navigation Core First was implemented for the four non-Menu Wave 2A navigation rows:

- App bar
- Nav bar
- Nav rail
- Tabs

Menu remains deferred to Wave 2A-2 because it is DISTINCT but COUPLED with the closed `popover/` provider contract. This Phase 2 does not create a `menu/` module and does not edit `modules/popover/*`.

Index note: `#N` references in this cycle mean Component Coverage Map TOC indices from `docs/v3.5.0/COMPONENT-COVERAGE-MAP.md`, not BACKLOG.md item numbers.

## Phase 1 P3 Decisions

### Tabs Ripple Option

Phase 1 P3-1 asked Phase 2 to choose between:

- option (i): static state-layer only
- option (ii): bounded animated ripple

Decision: option (ii).

Tabs uses bounded animated ripple by declaring `data-ax-ripple="bounded"` on tab hosts and consuming the existing `modules/ripple/` provider unchanged. This matches the Phase 1 ripple matrix where Tabs is a bounded TARGET. The runtime cost remains local to the Tabs pattern page: `lab-tabs.js` owns tab selection and keyboard state only; ripple attachment remains the existing provider's responsibility.

App bar action slots remain CANDIDATE. No App bar animated ripple is attached in v3.6.8.

### Wave 2A-2 Menu Timing

Phase 1 P3-2 is deferred to Phase 5. The close docs should decide whether Wave 2A-2 Menu is the next candidate or whether Wave 2B / BACKLOG #21 / narrowed #41 / residual #44 work takes priority.

## Files Added

Twenty-two lab files were added.

### App Bar

- `products/reference-implementations/axismundi-lab/modules/app-bar/lab-app-bar.css`
- `products/reference-implementations/axismundi-lab/modules/app-bar/lab-app-bar-pattern.html`
- `products/reference-implementations/axismundi-lab/modules/app-bar/docs/APP-BAR-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/app-bar/docs/APP-BAR-MEASUREMENT-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/app-bar/docs/APP-BAR-WP-MAPPING.md`

### Nav Bar

- `products/reference-implementations/axismundi-lab/modules/nav-bar/lab-nav-bar.css`
- `products/reference-implementations/axismundi-lab/modules/nav-bar/lab-nav-bar-pattern.html`
- `products/reference-implementations/axismundi-lab/modules/nav-bar/docs/NAV-BAR-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/nav-bar/docs/NAV-BAR-MEASUREMENT-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/nav-bar/docs/NAV-BAR-WP-MAPPING.md`

### Nav Rail

- `products/reference-implementations/axismundi-lab/modules/nav-rail/lab-nav-rail.css`
- `products/reference-implementations/axismundi-lab/modules/nav-rail/lab-nav-rail-pattern.html`
- `products/reference-implementations/axismundi-lab/modules/nav-rail/docs/NAV-RAIL-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/nav-rail/docs/NAV-RAIL-MEASUREMENT-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/nav-rail/docs/NAV-RAIL-WP-MAPPING.md`

### Tabs

- `products/reference-implementations/axismundi-lab/modules/tabs/lab-tabs.css`
- `products/reference-implementations/axismundi-lab/modules/tabs/lab-tabs.js`
- `products/reference-implementations/axismundi-lab/modules/tabs/lab-tabs-pattern.html`
- `products/reference-implementations/axismundi-lab/modules/tabs/docs/TABS-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/tabs/docs/TABS-MEASUREMENT-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/tabs/docs/TABS-RUNTIME-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/tabs/docs/TABS-WP-MAPPING.md`

This report is the twenty-third Phase 2 file.

## Implementation Summary

### App Bar

App bar adds static small, medium, large, and scrolled-density specimens. The module uses `.lab-app-bar-demo` as its lab-only scope and does not include JavaScript. Material Symbols action markup follows the existing icon policy, but action-slot ripple remains CANDIDATE and unpromoted.

### Nav Bar

Nav bar adds mobile bottom-navigation specimens with active, inactive, badge, and disabled states. Interactive destination hosts declare `data-ax-ripple="bounded"` and consume the existing ripple provider unchanged. The module uses `.lab-nav-bar-demo` as its lab-only scope.

### Nav Rail

Nav rail adds collapsed and expanded specimens with active, inactive, badge, and disabled states. Interactive destination hosts declare `data-ax-ripple="bounded"` and consume the existing ripple provider unchanged. The module uses `.lab-nav-rail-demo` as its lab-only scope and does not claim Navigation drawer or Sheet closure.

### Tabs

Tabs adds primary, secondary, icon, disabled, and panel specimens. The module uses `.lab-tabs-demo` as its lab-only scope. `lab-tabs.js` implements component-local tab behavior:

- `role="tablist"`, `role="tab"`, and `role="tabpanel"` are preserved.
- Click activates enabled tabs.
- ArrowRight and ArrowLeft move across enabled tabs.
- Home and End move to the first or last enabled tab.
- Activation updates `aria-selected`, `tabindex`, `.is-active`, and panel `hidden` state.
- No global keyboard listener is registered.

Tabs declares `data-ax-ripple="bounded"` on tab hosts and consumes the existing ripple provider unchanged.

### Menu

No `menu/` module was added. Menu remains routed to Wave 2A-2 because Menu owns menu semantics while `popover/` owns anchor, positioning, dismissal, outside-click, Escape, focus restore, and viewport collision behavior.

## Selector And Provider Boundaries

All new CSS is lab-scoped under one of:

- `.lab-app-bar-demo`
- `.lab-nav-bar-demo`
- `.lab-nav-rail-demo`
- `.lab-tabs-demo`

No new CSS overrides unscoped `.app-bar`, `.nav-bar`, `.nav-rail`, `.tabs`, `[data-ax-ripple]`, or `.material-symbols-rounded`.

Provider modules were not changed:

- `modules/popover/*`
- `modules/ripple/*`
- `modules/icon-system/*`

The existing `components.css` file remains unchanged for the entire file, including Section 0.

## Files Intentionally Unchanged

- `AGENTS.md`
- `CLAUDE.md`
- `products/reference-implementations/axismundi-pilot/theme.json`
- `products/reference-implementations/axismundi-pilot/functions.php`
- Pilot bridge source and asset CSS/JS pairs
- Pilot fixtures
- `products/reference-implementations/axismundi-lab/stylesheets/components.css`
- `products/reference-implementations/axismundi-lab/stylesheets/blocks.css`
- `products/reference-implementations/axismundi-lab/modules/popover/*`
- `products/reference-implementations/axismundi-lab/modules/ripple/*`
- `products/reference-implementations/axismundi-lab/modules/icon-system/*`
- `styleguide/*` after publish validation artifacts were restored
- `tools/validators/validate_theme_pilot.py`

## Sanity Checks

Pattern pages were opened from file URLs in a fresh Playwright Chromium context.

| Pattern | Console errors | Extra check |
|---|---:|---|
| App bar | 0 | Static page loaded |
| Nav bar | 0 | Ripple consumer markup present |
| Nav rail | 0 | Ripple consumer markup present |
| Tabs | 0 | ArrowRight moved from Feed to Chat |

Tabs runtime details after ArrowRight:

- `#tab-chat[aria-selected]`: `true`
- `#tab-feed[tabindex]`: `-1`
- `#panel-chat.hidden`: `false`
- `#panel-feed.hidden`: `true`

## Validation

| Command | Result |
|---|---|
| `wp-env run cli wp core version` | PASS - 7.0 |
| `python tools\generators\build_pilot_specimen_wall.py` | PASS - wall page 29 and editor smoke page 41 updated |
| `npm run validate:specimen-wall` | PASS - specimen wall render gate |
| `php -l products\reference-implementations\axismundi-pilot\functions.php` | PASS |
| `npm test` | PASS - overall 1.000; Axis A/B/C/D/E/F/G all 1.000 |
| `npm run validate:computed` | PASS |
| `npm run publish:styleguide` | PASS - publish mirror generated, then restored to keep Phase 2 write scope clean |
| `git diff --check` | PASS |

Validator-generated tracked reports and publish mirror files were restored after validation. No validator or styleguide artifact churn remains in the intended Phase 2 diff.

## Lock And Scope Preservation

- Lock 1 preserved: no `wp-custom` source route changed; Axis G stayed 1.000.
- Lock 2 preserved: token and md-sys/md-ref routes unchanged; Axis E stayed 1.000.
- Lock 3 preserved: WP core/button semantic route was not reopened.
- Lock 4 preserved: Menu/popover coupling was routed instead of collapsed into this slice.
- Lock 5 not promoted: diagnostic-first remains a methodology finding, and this Phase 2 does not edit `AGENTS.md` or `CLAUDE.md`.

## Phase 3 Expected QA

Phase 3 should be a visual and interaction confirmation pass:

- Open all four pattern pages at desktop and mobile widths.
- Confirm no console/page errors.
- Confirm App bar small/medium/large/scrolled variants frame correctly.
- Confirm Nav bar active/badge/disabled states and bounded ripple attachment.
- Confirm Nav rail collapsed/expanded variants and bounded ripple attachment.
- Confirm Tabs click and keyboard behavior: ArrowLeft, ArrowRight, Home, End, disabled-skip, and panel visibility.
- Re-run the standard validation commands and keep styleguide/generated artifacts out of the final diff unless review explicitly changes the write scope.
