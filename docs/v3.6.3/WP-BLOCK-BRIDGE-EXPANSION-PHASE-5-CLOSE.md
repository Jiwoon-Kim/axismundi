# v3.6.3 WP Block Bridge Expansion - Phase 5 Close

Date: 2026-05-20

## Verdict

v3.6.3 is closed as the first BACKLOG #41 implementation/routing slice.

The release consumed the v3.6.2 specimen wall evidence, applied one reset patch,
added three CSS-only bridge patches, and routed two semantic-decision findings
without implementing custom blocks or plugin behavior.

## Closed Scope

Implemented:

```txt
Reset:
  table-footer-contrast
    core/table tfoot no longer keeps the native 3px currentColor border.

Bridge:
  search-styleguide-delta
    core/search.is-style-filled-search receives the M3 search surface bridge.

  code-long-line-overflow
    core/code, core/preformatted, and post-content pre receive horizontal
    overflow behavior for long code lines.

  separator-variant-visibility
    default, wide, dots, inset, and middle-inset separator variants are visible
    and token-routed.
```

Routed:

```txt
Semantic decisions:
  button-anchor-semantics
    core/button anchor output remains valid navigation when href is present.
    M3 button visual bridge is allowed after the semantic route is named.

  quote-pullquote-semantics
    core/quote and core/pullquote remain distinct theme-owned surfaces.
    Future CSS must not silently collapse pullquote into generic quote styling.
```

Housekeeping:

```txt
- Corrected the mistaken BACKLOG #41 / #44 / #14 separator to Material Symbols
  font cross-link.
- Added .gitattributes line-ending policy:
  * text=auto eol=lf
```

## Documents

```txt
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
```

## Methodology Finding

v3.6.3 proves that BACKLOG #41 should stay split by evidence type:

```txt
reset                 -> small computed reset patch
bridge                -> CSS-only WordPress core block bridge patch
semantic-decision     -> explicit route first, then optional visual cleanup
plugin/custom-block   -> route only, never implement inside the theme cycle
```

This prevented bridge work from becoming a custom block implementation cycle
and prevented semantic mismatches from being silently hidden behind CSS.

## Lesson Locks

These are now close-time rules, not suggestions.

```txt
Lock 3 - core/button semantic route before visual cleanup

Before accepting visual cleanup for core/button link affordances, name the
semantic route. A core/button anchor with href is navigation and may receive an
M3 button visual bridge. A real action, form behavior, AJAX flow, federation
action, or durable custom schema must be routed to plugin/custom-block
territory, not implemented in the theme bridge.
```

```txt
Lock 4 - semantic mismatch handling rule

When a WordPress core block visually maps to M3 but carries divergent markup,
interaction, or accessibility semantics, route the mismatch as either
theme-owned semantic-decision or plugin/custom-block territory before
accepting a visual fix. Do not silently ignore the mismatch and do not collapse
distinct core block structures into one generic CSS patch.
```

## Carry-Forward Notes

```txt
Separator dots:
  Phase 2 used ASCII periods for the visible dots marker. A future polish pass
  may consider middle-dot or bullet glyphs.

Post-content pre:
  The code overflow bridge intentionally reaches post-content pre, not only
  block-rendered core/code and core/preformatted wrappers.

core/button without href:
  The v3.6.3 route is correct for anchors with href. An unconfigured
  core/button that renders an anchor without href is a content authoring issue,
  not a bridge defect.
```

## BACKLOG #41 State

BACKLOG #41 remains open but narrowed.

Closed by v3.6.3:

```txt
table-footer-contrast
search-styleguide-delta
code-long-line-overflow
separator-variant-visibility
button-anchor-semantics route
quote-pullquote-semantics route
```

Deferred:

```txt
button mechanical cleanup after route:
  text-decoration, user-select, and state styling checks for .wp-block-button__link

quote/pullquote implementation after route:
  selector narrowing and distinct .wp-block-pullquote bridge styling

ripple/editor parity:
  original #41 broader state/ripple/editor-canvas questions
```

## Final Validation

Final close checks:

```powershell
python tools\generators\build_pilot_specimen_wall.py                    PASS
npm run validate:specimen-wall                                          PASS
php -l products\reference-implementations\axismundi-pilot\functions.php PASS
npm test                                                                PASS
npm run validate:computed                                               PASS
git diff --check                                                        PASS
```

## Next Route

Next cycle candidates are deliberately not auto-started:

```txt
Primary:
  BACKLOG #41 residual bridge cleanup, now that v3.6.3 routed semantic items.

Follow-on:
  BACKLOG #44 specimen follow-on coverage + editor compatibility.

Alternative:
  Wave 2 plan-first.
  BACKLOG #21 Interpreter Plugin strategy.
```

