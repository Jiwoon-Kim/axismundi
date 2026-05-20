# v3.6.3 — WP Block Bridge Expansion — Phase 2 Report

Date: 2026-05-20

## Verdict

Phase 2 bridge patches are complete for the three v3.6.2 visual bridge inputs:

```txt
1. search-styleguide-delta
2. code-long-line-overflow
3. separator-variant-visibility
```

The work stayed in CSS bridge territory. No custom blocks, no `theme.json`
edits, no fixture expansion, and no semantic-decision fixes were performed.

## Phase 2 Entry Checkpoint

Per the Phase 0 review follow-up, `functions.php` was inspected before any
separator CSS work.

Current `core/separator` registrations:

```txt
Registered by axismundi-pilot:
  divider-inset
  divider-middle-inset

WordPress core-provided / native styles:
  default
  wide
  dots
```

Decision:

```txt
Phase 2 remains CSS-only.
```

Rationale:

```txt
The missing separator visibility work is style-variation skinning. It does not
need a custom block, durable schema, editor UI, or new content model.
```

## Changed

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
```

The source bridge and copied Pilot asset bridge were kept in lockstep.

## Bridge 1 — Search

Input:

```txt
search-styleguide-delta
```

Patch:

```txt
core/search .is-style-filled-search now gets the Search bar host affordances:
  - relative positioning and isolation
  - cursor:text
  - background / shadow transition
  - host state-layer pseudo-element
  - hover state-layer opacity
  - focus-within outline using secondary
  - input outline/font reset
  - native search cancel decoration suppression
```

This keeps the native WordPress search form contract intact. It does not add
suggestions, autocomplete, query behavior, search history, or federated search.

Computed evidence:

```txt
search-filled:
  display:        flex
  size:           390 x 56
  background:     rgb(236, 230, 240)
  box-shadow:     level3 shadow
  focus outline:  2px solid rgb(98, 91, 113)
  ::before:       content "", opacity 0 at rest
```

## Bridge 2 — Code

Input:

```txt
code-long-line-overflow
```

Patch:

```txt
core/code / pre bridge now explicitly sets:
  overflow-x: auto
  max-inline-size: 100%
  code white-space: pre
```

This carries the proven prose long-line behavior into the WordPress-rendered
core/code bridge without changing the v3.6.2 fixture.

Computed evidence from a temporary long-line DOM probe:

```txt
long core/code pre:
  overflow-x:  auto
  width:       390
  scrollWidth: 3680
  clientWidth: 390

long code child:
  white-space: pre
```

## Bridge 3 — Separator

Input:

```txt
separator-variant-visibility
```

Patch:

```txt
core/separator variants now map in Pilot post content:
  default                    25% centered line
  is-style-wide              100% line
  is-style-dots              visible textual dot marker, no line background
  is-style-divider-inset     inset by space-md
  is-style-divider-middle-inset inset by space-xl
```

`separator-default` was classified as `no-action` in v3.6.2, but Phase 2
intentionally adjusts it to a 25% centered line so default / wide / inset /
middle-inset read as one coherent variant family. This is a coherence-driven
bridge adjustment, not a newly discovered reset leak.

This deliberately does not route separator visibility through the Material
Symbols font issue.

Computed evidence:

```txt
separator-default:
  width:      100
  height:     1
  background: rgb(202, 196, 208)

separator-inset:
  width:      358
  height:     1
  background: rgb(202, 196, 208)

separator-middle-inset:
  width:      326
  height:     1
  background: rgb(202, 196, 208)
```

Additional temporary DOM probe for variants not yet in the committed v3.6.2
fixture:

```txt
wide:
  width:      390
  height:     1
  background: rgb(202, 196, 208)

dots:
  width:      390
  height:     24
  background: transparent
  color:      rgb(73, 69, 79)
  ::before:   "..."
```

## Fixture Boundary

The committed v3.6.2 fixture was not expanded in Phase 2.

```txt
Covered by committed fixture:
  separator-default
  separator-inset
  separator-middle-inset

Checked by temporary DOM probe only:
  separator-wide
  separator-dots
  long-line core/code
```

This preserves the Phase 0 non-goal that fixture expansion belongs to #44
unless explicitly promoted.

## Validation

```powershell
python tools\generators\build_pilot_specimen_wall.py
  PASS — updated specimen wall page 29

npm run validate:specimen-wall
  PASS

php -l products\reference-implementations\axismundi-pilot\functions.php
  PASS

npm test
  PASS — overall 1.000; Axis E/F/G all 1.000

npm run validate:computed
  PASS

git diff --check
  PASS
```

## Non-Goals Confirmed

```txt
- No custom block registration.
- No core/button semantic fix.
- No core/quote or core/pullquote semantic fix.
- No semantic mismatch silently collapsed into CSS.
- No fixture expansion.
- No theme.json edit.
- No lab baseline or published styleguide edit.
- No Material Symbols font routing for separator visibility.
```

## Next Route

Phase 3 should create:

```txt
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
```

Required semantic-decision routes:

```txt
1. button-anchor-semantics
2. quote-pullquote-semantics
```

Per Phase 0, any plugin/custom-block need must be routed, not implemented.
