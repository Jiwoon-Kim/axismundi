# v3.6.12 Wave 2B-3 Date+Time #22+#23 - Phase 0 Plan

Status: Phase 0 plan submitted for review
Mode: plan-first, Lock 5 applies
Candidate: Wave 2B-3 Date+Time #22+#23 PARTIAL completion
Baseline: local HEAD `a28f96c` after v3.6.11 close; origin/main baseline remains `2baecbb`

## Verdict

Proceed with a diagnostic-first Phase 1 for Date+Time #22+#23.

This cycle should decide whether the existing `date-time/` module can move from
PARTIAL to DONE in one bounded implementation cycle, or whether the remaining
surface must split into smaller follow-ons.

No implementation files are changed in Phase 0.

## Source Inputs

Primary handoff and current-state inputs:

- `NEXT-SESSION.md` post-v3.6.11 reading order and next-route recommendation.
- `CURRENT-STATE.md` v3.6.11 closed state.
- `CHANGELOG.md` latest v3.6.11 entry.
- `ROADMAP.md` tail: Wave 2B-3 Date+Time #22+#23 PARTIAL completion is the primary next route.
- `BACKLOG.md` entries relevant to Date+Time, especially BACKLOG #19.
- `AGENTS.md` and `CLAUDE.md`, including promoted Lock 5.
- `PROJECT-CONTEXT.md`.

Component and module inputs:

- `docs/v3.5.0/COMPONENT-COVERAGE-MAP.md`.
- `docs/v3.5.0/MODULE-STATUS-MATRIX.md`.
- `docs/v3.5.0/PROMOTION-CRITERIA.md`.
- `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md`.
- `products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.css`.
- `products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js`.
- `products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time-pattern.html`.
- `products/reference-implementations/axismundi-lab/modules/date-time/docs/DATE-TIME-AUDIT.md`.

Controlling prior cycle decisions:

- v3.6.10 closed Wave 2B-1 Form controls and promoted Lock 5.
- v3.6.11 closed Wave 2B-2 Dialog / Sheet and proved first Lock 5 self-application.
- v3.6.11 routed Wave 2B-3 Date+Time as the next primary candidate.

## Candidate Definition

Date+Time is the combined `#22+#23` row in the component coverage map, not two
independent rows for this cycle.

Current matrix status:

- Date picker #22: PARTIAL, existing `date-time/`, Full-Spec + Interaction.
- Time picker #23: PARTIAL, existing `date-time/`, folded into the same module.
- Provider relationship: listed as a `popover/` consumer in the module status matrix.
- Historical module state: v3.4.7 extraction with one legacy audit document and a
  large self-contained interaction runtime.

This cycle is not a blank module creation cycle. It is a PARTIAL-to-DONE
completion candidate for an existing module.

## Lock 5 Compliance

Lock 5 requires the diagnostic to identify source inputs, relevant baseline /
provider / semantic boundaries, route buckets, selected route, rejected routes,
write scope, fences, and validation plan before implementation.

Phase 0 establishes the diagnostic frame:

- Source inputs: listed above.
- Baseline boundaries: current `components.css` Date/Time baseline and the legacy
  audit's stale section references must be reconciled in Phase 1.
- Provider boundaries: `popover/` is listed as the provider in the matrix, but the
  existing module appears self-contained. Phase 1 must resolve this factual
  contract without editing `popover/`.
- Semantic boundaries: Date grid a11y, Time selection a11y, range selection,
  locale/timezone, persistence, plugin binding, and mobile full-screen variants
  must be classified separately.
- Route buckets: A-F below.
- Selected route: Phase 1 must choose after inventory.
- Rejected routes: Phase 1 must record evidence for each rejected bucket.
- Write scope: conditional Phase 2 scopes below.
- Fences: no-touch files listed below.
- Validation plan: standard validation plus Date+Time keyboard/form/overlay QA.

No safe-shortcut exception is used.

## Existing Baseline Boundary

Phase 1 must reconcile an important section-number mismatch:

- The legacy `DATE-TIME-AUDIT.md` refers to `components.css` sections `§33` and
  `§34`.
- Current `components.css` formal numbering is known to end earlier, with Date
  and Time selectors appearing in later chunk ranges rather than formal `§33` /
  `§34` headers.
- v3.6.10 Phase 1 previously identified Date picker around Chunk H3 and Time
  picker around Chunk H4. v3.6.12 Phase 1 must re-check the exact current HEAD
  line ranges before any implementation.

Baseline rule:

- Do not edit `components.css`.
- Do not promote new tokens or baseline primitives in this cycle.
- Any need to change baseline CSS is a stop-and-return condition.

## Provider Boundary

The matrix says Date+Time consumes `popover/`.

The existing `date-time/` module must be inventoried before deciding what that
means in practice:

- It may already be self-contained and merely popover-like.
- It may need documentation alignment without provider edits.
- It may need a real `popover/` consumer migration, which is a higher-risk route
  and requires review before implementation.

Provider rule:

- Do not edit `popover/`, `ripple/`, or `icon-system/`.
- Do not create a new shared overlay, focus, date, or time provider in Phase 2
  without returning for review.
- Do not reinterpret Date+Time as a generic plugin or WordPress editor binding.

## Semantic Boundary

Date+Time contains several distinct semantic surfaces:

- Date grid keyboard and screen-reader contract.
- Time picker selection contract.
- Range selection UI and preview.
- Month/year announcements.
- Locale calendar and timezone behavior.
- Recurring event or ActivityPub event semantics.
- WordPress editor sidebar, post meta, or plugin persistence.
- Mobile full-screen picker behavior.

Only the bounded lab module completion path is in scope. Plugin, persistence,
locale, timezone, recurring-event, and WordPress binding work are out of scope.

The user's note that full-screen Dialog is not yet a true full-screen pattern is
also out of scope for this Date+Time cycle; it can route as a Dialog follow-on if
needed later.

## Route Buckets

### Route A - Full Date+Time Completion

Complete the existing `date-time/` module as a combined Full-Spec + Interaction
Runtime surface.

Expected shape:

- Keep work inside `modules/date-time/`.
- Close the real remaining Date grid a11y gaps if Phase 1 confirms they are
  bounded and still present.
- Clarify Time picker semantics without over-expanding into locale/timezone or
  plugin behavior.
- Add or update audit docs to match modern Wave 2 module standards.
- Preserve baseline and provider fences.

Route A is the preferred hypothesis only if Phase 1 proves the remaining gaps are
local and bounded.

### Route B - Date Grid A11y First

Prioritize BACKLOG #19 date-grid keyboard and screen-reader completion.

This route may leave Time picker or range-selection refinements as PARTIAL
follow-ons if Phase 1 shows Date grid a11y is the blocking critical defect.

### Route C - Documentation / Audit Completion Only

If implementation behavior is already better than the legacy audit says, update
the module documentation surface and close only the documentation mismatch.

This route must not claim DONE if Phase 1 still finds active keyboard or a11y
gaps that block Full-Spec + Interaction completion.

### Route D - Popover Consumer Alignment

If Phase 1 proves Date+Time must be realigned with the existing `popover/`
provider contract, stop before implementation and return for review.

Route D may become a later implementation route, but it is not pre-authorized for
Phase 2 because provider-consumer migration can affect a closed provider.

### Route E - Split / No-Code Routing

If the remaining work is too broad, Phase 1 may split the cycle into explicit
follow-ons, such as:

- Wave 2B-3a Date grid a11y.
- Wave 2B-3b Time picker semantics.
- Wave 2B-3c range selection or mobile variant.

Use this route if a single PARTIAL-to-DONE cycle would collapse unrelated
semantics.

### Route F - Other Evidence-Backed Route

Use only with concrete Phase 1 evidence and explicit review.

## Phase 1 Inventory Tasks

Phase 1 must complete these tasks before any patch:

1. Reconfirm `modules/date-time/` file set, line counts, and current behavior.
2. Reconcile legacy audit claims with current HEAD code and pattern HTML.
3. Re-map Date picker baseline selectors in `components.css` with exact line
   ranges.
4. Re-map Time picker baseline selectors in `components.css` with exact line
   ranges.
5. Record the section/chunk disambiguation for the stale `§33` / `§34`
   references.
6. Inventory pattern HTML roles, labels, hidden surfaces, dialog/popover-like
   hosts, range preview, and theme scripts.
7. Inventory `lab-date-time.js` runtime: keyboard map, roving tabindex,
   aria-current, live month/year announcements, commit behavior, range selection,
   and time selection.
8. Compare actual code against BACKLOG #19 item by item.
9. Determine whether BACKLOG #19 is fully in scope for v3.6.12, partially in
   scope, already closed by code drift, or still deferred.
10. Determine whether the matrix `popover/` provider relationship is factual,
    aspirational, or stale.
11. Map Date vs Time vs Range vs Mobile vs Plugin responsibilities.
12. Decide whether existing legacy `DATE-TIME-AUDIT.md` remains a provenance doc,
    is updated, or is supplemented by modern audit docs.
13. Define the Phase 2 write scope and no-touch fences.
14. Define the Phase 3 keyboard, screen-reader-adjacent, visual, and overlay QA
    matrix.
15. Select Route A/B/C/D/E/F and record rejected-route evidence.

## Phase 2 Conditional Write Scope

No Phase 2 implementation is authorized until Phase 1 review approves a route.

Potential Route A/B write scope:

- `products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.css`
- `products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js`
- `products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time-pattern.html`
- `products/reference-implementations/axismundi-lab/modules/date-time/docs/DATE-TIME-AUDIT.md`
- Optional modern audit docs under `modules/date-time/docs/`, if Phase 1 chooses
  to supplement rather than rewrite the legacy audit:
  - `DATE-TIME-SPEC-AUDIT.md`
  - `DATE-TIME-MEASUREMENT-AUDIT.md`
  - `DATE-TIME-RUNTIME-AUDIT.md`
  - `DATE-TIME-WP-MAPPING.md`
- `docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-2-REPORT.md`

Potential Route C write scope:

- Documentation-only changes under `modules/date-time/docs/`.
- Phase 2 report.

Potential Route D write scope:

- None in Phase 2 without renewed review.

## Files Not Expected To Change

Lock and process files:

- `AGENTS.md`
- `CLAUDE.md`

WordPress / Pilot files:

- `products/reference-implementations/axismundi-pilot/theme.json`
- `products/reference-implementations/axismundi-pilot/functions.php`
- `products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css`
- `products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js`
- `products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css`
- `products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js`
- `products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html`
- `products/reference-implementations/axismundi-pilot/fixtures/core-block-editor-smoke.html`

Baseline and styleguide surfaces:

- `products/reference-implementations/axismundi-lab/components.css`
- `products/reference-implementations/axismundi-lab/blocks.css`
- `products/reference-implementations/axismundi-lab/style-guide.html`
- `products/reference-implementations/axismundi-lab/scripts/style-guide.js`
- `products/reference-implementations/axismundi-lab/scripts/theme.js`

Provider modules:

- `products/reference-implementations/axismundi-lab/modules/popover/`
- `products/reference-implementations/axismundi-lab/modules/ripple/`
- `products/reference-implementations/axismundi-lab/modules/icon-system/`

Closed Wave 2A / Wave 2B modules:

- `products/reference-implementations/axismundi-lab/modules/app-bar/`
- `products/reference-implementations/axismundi-lab/modules/nav-bar/`
- `products/reference-implementations/axismundi-lab/modules/nav-rail/`
- `products/reference-implementations/axismundi-lab/modules/tabs/`
- `products/reference-implementations/axismundi-lab/modules/menu/`
- `products/reference-implementations/axismundi-lab/modules/checkbox/`
- `products/reference-implementations/axismundi-lab/modules/radio/`
- `products/reference-implementations/axismundi-lab/modules/switch/`
- `products/reference-implementations/axismundi-lab/modules/dialog/`
- `products/reference-implementations/axismundi-lab/modules/sheet/`

Other existing modules:

- `products/reference-implementations/axismundi-lab/modules/text-field/`
- `products/reference-implementations/axismundi-lab/modules/search-bar/`
- `products/reference-implementations/axismundi-lab/modules/button/`
- `products/reference-implementations/axismundi-lab/modules/button-group/`
- `products/reference-implementations/axismundi-lab/modules/fab/`

Validators and generators:

- `tools/validators/validate_theme_pilot.py`
- `tools/validators/validate_pilot_specimen_wall.js`
- `tools/generators/build_pilot_specimen_wall.py`

## Stop-And-Return Conditions

Stop and return for review before implementation if Phase 1 or Phase 2 shows a
need to:

1. Edit `components.css` or add baseline Date/Time primitives.
2. Edit `popover/`, `ripple/`, `icon-system/`, or create a shared provider.
3. Edit WordPress / Pilot files, fixtures, bridge files, or plugin-tier binding.
4. Edit AGENTS.md / CLAUDE.md or reinterpret Lock 5.
5. Convert Date+Time into a new generalized overlay/focus/calendar provider.
6. Add timezone, locale calendar, recurring-event, ActivityPub, post-meta, or
   editor-sidebar binding behavior.
7. Treat mobile full-screen Date/Time picker behavior as mandatory for closing
   this cycle without explicit review.

## Risk Register

### R1 - Legacy Audit Drift

The v3.4.7 audit may be stale relative to current code. Phase 1 must compare
claims to HEAD behavior before treating any gap as active.

### R2 - BACKLOG #19 Scope Explosion

Date grid a11y can become large. Phase 1 must separate required Date grid closure
from Time picker refinements, range selection, mobile variants, and screen-reader
manual testing.

### R3 - Popover Provider Boundary

The matrix says `popover/` provider, but existing code may be self-contained.
Provider realignment is not pre-authorized.

### R4 - Plugin / WordPress Scope Bleed

Timezone, locale calendars, recurring events, ActivityPub, editor sidebar, post
meta, and persistence are plugin/application concerns, not lab module closure.

### R5 - Baseline Section Mismatch

Legacy references to `components.css §33/§34` can mislead implementation. Phase 1
must record current line-based mapping.

### R6 - Time Picker A11y Ambiguity

Time picker semantics may require radiogroup/listbox-like decisions. Phase 1 must
avoid faking a role without evidence.

### R7 - Range Selection Overreach

Range selection may require its own a11y and persistence model. Do not let range
support silently expand the cycle.

### R8 - Mobile Full-Screen Variant Creep

Mobile full-screen picker behavior is useful but not automatically required for
Date+Time PARTIAL completion.

### R9 - Existing Runtime Size

`lab-date-time.js` is large enough that patching without diagnostics risks
regressions. Phase 1 must map current responsibilities first.

### R10 - Lock 5 Regression

This is the second post-promotion Lock 5 self-application cycle. Do not patch
before route selection.

### R11 - Validation Artifact Churn

Publish and validator outputs must be restored after validation.

### R12 - Follow-On Fragmentation

Only create BACKLOG items for concrete routed work. Otherwise, use ROADMAP /
CURRENT-STATE / NEXT-SESSION to carry Wave 2B-3 follow-ons.

## Validation Strategy

Standard validation after any approved Phase 2 implementation:

- `wp-env run cli wp core version`
- `python tools/generators/build_pilot_specimen_wall.py`
- `npm run validate:specimen-wall`
- `php -l products/reference-implementations/axismundi-pilot/functions.php`
- `npm test`
- `npm run validate:computed`
- `npm run publish:styleguide`
- `git diff --check`

Date+Time-specific Phase 3 QA should include:

- Desktop and mobile viewports.
- Light and dark themes, set programmatically if module pages omit `theme.js`.
- Console errors: 0.
- Horizontal overflow at 390px: 0.
- Date open / close behavior.
- Date grid keyboard behavior according to the route selected in Phase 1.
- Roving tabindex or selected-focus behavior, if implemented.
- `aria-current`, selected date, and live month/year announcements, if in scope.
- Enter / Space commit behavior.
- Time picker keyboard and selection behavior.
- Range preview behavior, if in scope.
- Focus restoration after close.
- Provider fence verification (`popover/`, `ripple/`, `icon-system/` unchanged).

## Phase Cadence

- Phase 0: plan-only, no implementation.
- Phase 1: diagnostic inventory and route selection.
- Phase 2: implementation only after review approval.
- Phase 3: visual / keyboard / interaction QA.
- Phase 4: unused.
- Phase 5: close report and state docs.

## Expected Phase 1 Output

Phase 1 should submit:

- Current `date-time/` factual inventory.
- BACKLOG #19 item-by-item status.
- Baseline section/chunk disambiguation.
- Provider-boundary decision.
- Date / Time / Range / Mobile / Plugin responsibility map.
- Route A/B/C/D/E/F selection and rejected-route evidence.
- Phase 2 write scope.
- Phase 3 QA matrix.
- Confirmation that Lock 5 was self-applied without a safe-shortcut exception.
