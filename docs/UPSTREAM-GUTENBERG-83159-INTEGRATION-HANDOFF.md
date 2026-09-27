# #83159 integration handoff

## Goal

Use the existing draft PR #83159 as the one public working surface for the
integrated Font capabilities prototype. Do not create another public PR.

The resulting draft combines the existing Font variations work with the
capability-driven Style, Weight, and Width work so reviewers can inspect the
whole typography model in one diff and one editor flow.

## Branch relationship

As checked on 2026-09-27:

```text
add/font-variation-settings (#83159)
  is an ancestor of
try/typography-axis-controls
```

`try/typography-axis-controls` is at `ebd7071d7d`.

Therefore, incorporate the prototype into `add/font-variation-settings` with
a normal merge. Do not force-push either branch. The merge should retain the
existing #83159 history and add the prototype commits after it.

## Public PR boundaries

Keep these separate:

| Item | Boundary |
| --- | --- |
| #83462 | Independent effective-family bug fix. Do not fold it into #83159. It preserves `style.typography.fontFamily` before capability lookup. |
| #83141 | Keep open for now. The integrated draft includes its weight work because the UI must be reviewed as a whole. If #83141 lands first, rebase #83159 onto trunk and remove the duplicate diff. If the integrated draft becomes the accepted direction first, explicitly supersede #83141 later. Do not close or edit #83141 in this handoff. |
| #83456 | Separate CSS `font-style` range parsing fix. Do not alter it here. The Slant control deliberately reads the raw face descriptor for its range. |

## What #83159 should contain after integration

1. The existing `fontVariationSettings` value path and separate Font variations panel.
2. `fontStretch` style-property plumbing, stored as `fontStretch` and output as `font-stretch`.
3. Typography panel order: Color, Font, Style, Weight, Width, Size, then layout controls.
4. Independent Style, Weight, and Width panel items rather than the combined Appearance item.
5. A single `resolveFontFaceCapabilities()` interpretation of `fontFace[]`:
   - Static coverage points create only actual face choices.
   - A declared interval creates a slider and typed numeric input.
   - New typed values clamp to the declared interval.
   - Existing out-of-range values are preserved and explained.
   - A static `normal` plus `condensed` family must offer only Normal and Condensed, with no custom-width control.
6. Capability-only Style behavior: no faux Italic in the Style discovery control; Oblique exposes a bounded Slant control using the raw CSS face descriptor.
7. Shared `FontAxisRangeControl` for the numeric slider, number input, clamping, and out-of-range notice. Keep preset selection and storage conversion with each owning axis control.

## Architecture that must remain intact

```text
Typography
  Color
  Font
  Style
  Weight
  Width
  Size and text-layout controls

Font variations
  Custom axes such as Grade, FILL, XTRA, and Optical size
```

- `Style`, `Weight`, and `Width` are CSS-property owners. Their availability
  comes from the selected effective font family and its faces, not from
  per-property block visibility policy.
- Text layout remains block policy.
- Font variations remains a separate panel. Its `axes` contract governs
  custom-axis editing; it is not the source of the high-level CSS-property UI.
- `axes` uses `tag`, `min`, `max`, optional `default`, and optional `name`.
  Do not add `step` or a UI `control` field.
- Do not insert ad hoc visual headings inside `ToolsPanel`. The current
  ordering is intentional; a real group component would be a separate
  upstream component change.

## Important distinctions

```text
Appearance / capability controls = discovery of real faces
Toolbar Bold and Italic          = formatting requests, which may synthesize
```

Do not implement the faux Bold policy in this handoff. It is a later,
separate decision: Appearance should eventually show only actual weight
coverage, while formatting can request `font-weight: bolder`.

For Slant, keep new inputs inside the CSS `oblique` range declared by the
face. Existing values outside the range are retained with a neutral warning:
the browser may synthesize the requested style.

## Required verification

Do not make public PR changes, change draft state, post comments, or rewrite
PR text. Prepare only local branches, commits, and a proposed #83159 body.

Run the focused tests already used by this work:

```text
npm run typecheck
npm run lint:js
Typography, global-styles, style-engine, and utility tests affected by the changes
```

If the local build environment is available, also verify these editor cases:

1. Roboto Flex: Style offers Normal and Oblique, not faux Italic; Slant is
   bounded by `oblique 0deg 10deg`.
2. Roboto Flex Width: declared `25% 151%` bounds both slider and typed input.
3. Static Widths: Normal plus Condensed only; no custom toggle or slider.
4. Material Symbols: no Width item when its face declares no `fontStretch`.
5. Font variations is a separate panel and shows custom axes, not registered
   Style/Weight/Width axes.

## Deliverable

Report:

1. The merge commit and resulting local branch state.
2. Exact commits that form the proposed #83159 review story.
3. Test and VQA results.
4. A revised #83159 title and PR-body draft.
5. Any conflict or reason not to proceed.

Do not push `add/font-variation-settings` or mutate GitHub without explicit
user approval after this report.

## Execution record (2026-09-27)

Carried out in the Claude Code session that built the prototype, not by a
separate agent: the merge, the checks and the body draft were done with the
context already in hand.

```text
merge     02a87c926b   add/font-variation-settings <- try/typography-axis-controls
                       normal merge, no conflicts, no force-push, not pushed
size      27 commits / 70 files / +4140 -222 against trunk
checks    typecheck 0, lint:js 0, 766 tests across 63 files
body      docs/UPSTREAM-GUTENBERG-83159-INTEGRATED-BODY.md (verified, unpublished)
```

One boundary in this handoff could not be kept. The merge carries #83462's
commit `8d480467fd`, which preserves a font family written inline in a
block's style. That commit was written on the prototype branch first and
cherry-picked to #83462 afterwards, so it is an ancestor here and cannot be
separated without rewriting the branch. Reverting it would send the
capability lookup back to the inherited family for any block of that kind,
which is the first four verification cases. It stays, and the proposed body
says why; a rebase after #83462 lands drops it as the same patch.

The build and the five editor cases were run afterwards in a background
agent, with the toolchain requirement written into its instructions: both
the portable Node directory and the npm shim must be on `PATH`, or the
repository's `devEngines` check stops the build.
