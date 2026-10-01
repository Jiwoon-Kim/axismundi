# Material 3 Grids and Spacing Reference Notes

## Status

Derived reading aid. 2026-10-01.

This file is a concise implementation/VQA index derived from the supplied M3 references. The
unedited source belongs in `SOURCE-M3-GRIDS-SPACING-RAW.md`; Axismundi decisions belong in
`DECISION-FRONTEND-SPATIAL-FOUNDATION.md`.

## Sources

- [Grids and spacing overview](https://m3.material.io/foundations/layout/grids-spacing/overview)
- [Grids](https://m3.material.io/foundations/layout/grids-spacing/grids)
- [Spacing](https://m3.material.io/foundations/layout/grids-spacing/spacing)
- [Density](https://m3.material.io/foundations/layout/grids-spacing/density)
- [Breakpoints overview](https://m3.material.io/foundations/layout/breakpoints/overview)

## Reference points

```text
Grid
  adapts column count, width, and spacing across breakpoints
  positions rails and panes before product content
  uses margin, title, content, bar/safety, and rail rulers for alignment

Spacing
  groups related information, directs attention, and expresses product personality
  supports explicit grouping with boundaries and implicit grouping with proximity/open space
  creates rhythm, similarity, proximity, continuity, focal points, and negative space

Density
  concerns information visible in a space
  is a user-controlled choice, not an automatic breakpoint behavior
  keeps default interactive targets at least 48x48 CSS pixels
  separates layout density from component-internal density scaling
```

## Breakpoint source index

| Breakpoint | Source margin | Source pane spacer | Navigation/pane notes |
| --- | ---: | ---: | --- |
| Compact | 16dp | n/a | Navigation bar or modal expanded rail; one pane |
| Medium | 24dp | 24dp | Rail for single pane; navigation bar for two panes |
| Expanded | 24dp | 24dp | Collapsed or expanded rail; one or two panes |
| Large | 24dp | 24dp | Rail; one or two panes |
| Extra-large | 24dp | 24dp | Rail may expand; up to three panes |

The source recommends designing for available window space rather than device labels. It asks
each breakpoint transition to consider what should be revealed, divided, resized, repositioned,
or swapped. Swaps must be functionally equivalent.

## Not an Axismundi token list

This reference does not define CSS variable names, numbers, or a density setting for Social.
Those require implementation and VQA under `DECISION-FRONTEND-SPATIAL-FOUNDATION.md`.
