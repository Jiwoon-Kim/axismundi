# v3.6.7 Phase 3 Visual / Editor QA - WP Specimen Follow-On Editor Compatibility

Date: 2026-05-21

Status: GO for review

Decision under test: Route C split fixture strategy from Phase 2.

## Verdict

Phase 3 confirms the Route C implementation:

- The original front-end specimen wall still passes the Tier 1 render gate.
- The new editor smoke fixture stays editor-valid with `0 / 0 / 0 / 0` evidence.
- The original wall's editor-side `56 / 56` validation signal remains intentionally isolated on the front-end-only evidence surface.
- No implementation files were edited in Phase 3.

## Environment

| Item | Value |
|---|---|
| WordPress core | 7.0 |
| Existing specimen wall page | `29` |
| Existing specimen wall slug | `axismundi-core-block-specimen-wall` |
| Editor smoke page | `41` |
| Editor smoke slug | `axismundi-core-block-editor-smoke` |
| Browser context | Fresh Playwright Chromium context |

## Rebuild

Command:

```text
python tools\generators\build_pilot_specimen_wall.py
```

Result:

```text
Updated Axismundi Core Block Specimen Wall page 29: http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
Updated Axismundi Core Block Editor Smoke page 41: http://localhost:8888/?pagename=axismundi-core-block-editor-smoke
```

Both fixture pages were rebuilt from committed fixture files before validation.

## Integrated Validator

Command:

```text
npm run validate:specimen-wall
```

Result:

```text
specimen wall render gate PASS
```

The integrated validator exercised both the original front-end specimen wall and the new editor smoke fixture.

## Front-End Specimen Wall

Source: `tmp/phase1-specimen-wall/specimen-wall-render-gate.json`

| Check | Phase 2 | Phase 3 | Result |
|---|---:|---:|---|
| HTTP status | 200 | 200 | PASS |
| Console/page errors | 0 | 0 | PASS |
| Horizontal overflow | 0 | 0 | PASS |
| Tier 1 anchors | 11/11 | 11/11 | PASS |
| Findings | 0 | 0 | PASS |

The existing wall remains the stable front-end computed-style evidence surface.

## Editor Smoke Front End

Source: `tmp/phase1-specimen-wall/specimen-wall-render-gate.json`

| Check | Phase 2 | Phase 3 | Result |
|---|---:|---:|---|
| HTTP status | 200 | 200 | PASS |
| Console/page errors | 0 | 0 | PASS |
| Horizontal overflow | 0 | 0 | PASS |
| Sections | 6 | 6 | PASS |
| Button links | 5 | 5 | PASS |
| Search blocks | 2 | 2 | PASS |

The editor-valid fixture renders on the front end without console or layout regressions.

## Editor Smoke Editor Canvas

Source: `tmp/phase1-specimen-wall/specimen-wall-render-gate.json`

| Check | Phase 2 | Phase 3 | Result |
|---|---:|---:|---|
| Editor iframe count | 1 | 1 | PASS |
| Editor console/page errors | 0 | 0 | PASS |
| Block validation errors | 0 | 0 | PASS |
| Invalid-content UI text | 0 | 0 | PASS |
| Attempt recovery UI text | 0 | 0 | PASS |

The editor smoke fixture meets the Phase 2 acceptance target: `0 / 0 / 0 / 0`.

## Existing Wall Editor Reference

The original wall was opened in the block editor as a reference-only check. This is not the editor-valid surface.

| Check | Phase 1 | Phase 3 | Result |
|---|---:|---:|---|
| Editor iframe count | 1 | 1 | PASS |
| Console/page errors | 56 | 56 | PASS |
| Block validation errors | 56 | 56 | PASS |
| Invalid-content UI text | 0 | 0 | PASS |
| Attempt recovery UI text | 0 | 0 | PASS |

This confirms the Route C boundary: the existing wall still carries the intentional front-end-only fixture/save mismatch signal, while the new editor smoke fixture supplies editor-valid evidence.

## Coverage Follow-On Status

No follow-on coverage items were pulled into Phase 3.

| Item | Phase 3 Status | Routing |
|---|---|---|
| mark/highlight coverage | Open | Keep under BACKLOG #44 follow-on |
| long-line code coverage | Open | Fixture coverage only; do not reopen #41 bridge/reset work |
| deep pullquote coverage | Open | Future representative semantic surface candidate |
| Material Symbols | No active v3.6.7 defect | Keep #14 cross-reference; do not route as separator visibility work |

## Phase 2 P3 Notes

The Phase 2 reviewer notes remain non-blocking:

- `WP_ADMIN_USER` / `WP_ADMIN_PASS` fallback would improve portability, but wp-env default credentials remain valid for this local cycle.
- `sections >= 6` could become `sections === 6` in a future validator hardening pass.
- The fixed editor settle wait remains stable in this local Phase 3 run.
- `tmp/phase1-specimen-wall` is a cosmetic directory-name carryover.

No Phase 3 patch was made for these notes because the review gate authorized a no-implementation QA pass and all acceptance evidence is already stable.

## Locks And Scope

Lock 1 is preserved. Axis G remains `1.000`.

Lock 2 is preserved. Axis E remains `1.000`.

Lock 3 is preserved. Core/button semantic routing was not reopened.

Lock 4 is preserved. The original wall mismatch remains classified as fixture/save mismatch, not hidden by a visual patch.

Not changed in Phase 3:

- `tools/validators/validate_theme_pilot.py`
- `theme.json`
- `products/reference-implementations/axismundi-pilot/functions.php`
- `products/reference-implementations/axismundi-pilot/pilot-block-bridge.css`
- `products/reference-implementations/axismundi-pilot/pilot-block-bridge.js`
- `products/reference-implementations/axismundi-pilot/assets/pilot-block-bridge.css`
- `products/reference-implementations/axismundi-pilot/assets/pilot-block-bridge.js`
- `components.css` Section 0
- `styleguide/*`
- `modules/ripple/*`

## Validation

Commands:

```text
wp-env run cli wp core version
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
git diff --check
```

Results:

| Gate | Result |
|---|---|
| `wp core version` | PASS - 7.0 |
| Import fixtures | PASS |
| `validate:specimen-wall` | PASS |
| `php -l functions.php` | PASS |
| `npm test` | PASS - overall 1.000; Axis A/B/C/D/E/F/G all 1.000 |
| `validate:computed` | PASS |
| `git diff --check` | PASS |

Validator-generated tracked report files were restored after validation. No validator artifact churn remains.

## Recommendation

Proceed to Phase 5 close if review approves.

Phase 5 should:

- Mark Route C as the v3.6.7 implemented outcome.
- Record that BACKLOG #44 editor compatibility now has a split editor-valid smoke surface.
- Preserve mark/highlight, long-line code, deep pullquote, and Material Symbols as open follow-ons unless review asks otherwise.
- Mention the original wall editor `56 / 56` signal as intentionally retained on the front-end-only evidence fixture.
