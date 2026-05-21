# v3.6.7 Phase 2 Report - WP Specimen Follow-On Editor Compatibility

Date: 2026-05-21

Status: GO for review

Decision: Route C - split fixture strategy implemented.

## Verdict

Phase 2 implements the Route C outcome selected in Phase 1:

- Keep the existing `core-block-specimen-wall.html` as the front-end computed-style evidence surface.
- Add a separate editor-valid smoke fixture for WordPress block editor compatibility.
- Import both fixtures into wp-env with stable slugs.
- Extend the existing specimen wall validator so one validation command checks both surfaces.

No implementation theme files were edited.

## Phase 1 P3 Decisions

Phase 2 resolved the four pre-implementation reviewer notes as follows:

1. New editor-valid fixture file: `products/reference-implementations/axismundi-pilot/fixtures/core-block-editor-smoke.html`.
2. Validator strategy: extend `tools/validators/validate_pilot_specimen_wall.js` rather than adding a new validator or npm script.
3. New WordPress page slug: `axismundi-core-block-editor-smoke`.
4. Duplicate factor 2 note: the original wall still emits 28 unique fixture/save mismatches as 56 raw console errors in the current WordPress 7.0 editor. Phase 2 treats the raw duplicate factor as editor-evaluation noise and the unique mismatch set as the source mapping. The new editor-valid smoke surface is accepted on `0 / 0 / 0` editor evidence instead of a duplicate-factor-sensitive count.

## Files Changed

Added:

- `products/reference-implementations/axismundi-pilot/fixtures/core-block-editor-smoke.html`

Updated:

- `tools/generators/build_pilot_specimen_wall.py`
- `tools/validators/validate_pilot_specimen_wall.js`

Report:

- `docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md`

Not changed:

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

## Fixture Design

The new editor smoke fixture intentionally has no `data-ax-specimen-id` or `data-ax-specimen-variant` anchors. Those anchors remain owned by the front-end specimen wall because they are part of the v3.6.2 computed evidence method.

The editor smoke fixture uses WordPress core block serialization that the editor can parse and save without validation repair:

- core/group wrapper
- headings and paragraphs
- unordered and ordered lists
- quote
- code
- tables with `has-fixed-layout`
- five button style variants
- two search variants
- two separators
- columns with serialized paragraph inner blocks

This keeps editor compatibility evidence separate from front-end stable-anchor evidence.

## Importer Update

`build_pilot_specimen_wall.py` now imports two page fixtures:

| Fixture | Slug | Title |
|---|---|---|
| `core-block-specimen-wall.html` | `axismundi-core-block-specimen-wall` | `Axismundi Core Block Specimen Wall` |
| `core-block-editor-smoke.html` | `axismundi-core-block-editor-smoke` | `Axismundi Core Block Editor Smoke` |

The importer uses `wp post list` to find each page by slug, then updates existing pages or creates missing pages. In the local Phase 2 run, the existing front-end wall stayed on page `29`, and the new editor smoke page was created as page `41`.

## Validator Update

`validate_pilot_specimen_wall.js` still validates the original front-end specimen wall:

- HTTP status
- zero front-end console/page errors
- zero horizontal overflow
- Tier 1 family anchors
- expected variant and entry selectors
- mobile screenshot and JSON report

It now also validates the editor smoke fixture:

- front-end HTTP status
- zero front-end console/page errors
- zero horizontal overflow
- expected section/button/search counts
- editor page id resolution through the admin bar edit link
- editor iframe presence
- zero editor console/page errors
- zero block validation errors
- zero invalid-content UI text
- zero Attempt recovery UI text

The existing `npm run validate:specimen-wall` command remains the only specimen-wall validator entry point. No `package.json` change was needed.

## Phase 2 Evidence

Importer:

```text
Updated Axismundi Core Block Specimen Wall page 29: http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
Created Axismundi Core Block Editor Smoke page 41: http://localhost:8888/?pagename=axismundi-core-block-editor-smoke
```

Front-end specimen wall:

| Check | Result |
|---|---|
| URL | `http://localhost:8888/?pagename=axismundi-core-block-specimen-wall` |
| Render gate | PASS |
| Tier 1 anchors | 11/11 |
| Horizontal overflow | 0 |
| Console/page errors | 0 |

Editor smoke fixture:

| Check | Result |
|---|---|
| URL | `http://localhost:8888/?pagename=axismundi-core-block-editor-smoke` |
| Edit URL | `http://localhost:8888/wp-admin/post.php?post=41&action=edit` |
| HTTP status | 200 |
| Horizontal overflow | 0 |
| Front-end console/page errors | 0 |
| Sections | 6 |
| Button links | 5 |
| Search blocks | 2 |
| Editor iframe count | 1 |
| Editor console/page errors | 0 |
| Block validation errors | 0 |
| Invalid-content UI text count | 0 |
| Attempt recovery UI text count | 0 |

Acceptance target for the new editor-valid surface is met: `0 / 0 / 0`.

## Coverage Follow-On Status

These #44 coverage items were not patched in Phase 2. Route C establishes the editor-valid lane first.

| Item | Phase 2 Status | Routing |
|---|---|---|
| mark/highlight coverage | Open | Keep under #44 follow-on after Route C review |
| long-line code coverage | Open | Add as fixture coverage only; do not reopen #41 bridge/reset work |
| deep pullquote coverage | Open | Candidate for future representative semantic surface; no #41 reproof |
| Material Symbols | No active v3.6.7 defect | Preserve #14 cross-reference; do not route as separator visibility work |

## Locks And Scope

Lock 1 is preserved. No `wp-custom` source changes were made.

Lock 2 is preserved. No token files or `md-sys` / `md-ref` mappings were changed.

Lock 3 is preserved. Core/button semantic routing was not reopened.

Lock 4 is preserved. The 56/56 mismatch was treated as committed-fixture versus WordPress-save mismatch, not hidden by a visual patch.

This remains core block fixture/editor compatibility work, not plugin/custom-block territory.

## Validation

Commands:

```text
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
| Import fixtures | PASS |
| `validate:specimen-wall` | PASS |
| `php -l functions.php` | PASS |
| `npm test` | PASS - overall 1.000; Axis A/B/C/D/E/F/G all 1.000 |
| `validate:computed` | PASS |
| `git diff --check` | PASS |

Validator-generated tracked report files were restored after validation. No validator artifact churn remains in the working tree.

## Next

Submit this Phase 2 implementation and report for review.

If Phase 2 receives GO, Phase 3 should perform a compact visual/editor QA pass:

- Rebuild both fixture pages.
- Re-run `validate:specimen-wall`.
- Record the editor smoke `0 / 0 / 0` evidence again.
- Confirm the original front-end specimen wall still passes its Tier 1 render gate.
- Keep mark/highlight, long-line code, pullquote, and Material Symbols as routed follow-ons unless review asks to pull one into v3.6.7.
