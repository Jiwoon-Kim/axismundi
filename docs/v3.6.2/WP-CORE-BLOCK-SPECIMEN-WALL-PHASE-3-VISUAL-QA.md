# v3.6.2 — WP Core Block Specimen Wall — Phase 3 Visual QA

Date: 2026-05-20

## Verdict

Phase 3 visual QA is complete.

The specimen wall is valid as a front-end evidence surface. It also surfaced
editor, table, button, search, code, quote/pullquote, separator, and font/icon
constraints that should be routed forward rather than fixed inside v3.6.2.

This remains an evidence cycle. No bridge/reset CSS was patched during visual
QA.

## Surface

```txt
URL:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall

Modes reviewed:
  light
  dark

Baseline:
  Phase 2 computed snapshot covers 11 Tier 1 families and 26 entries.
```

## Visual / Computed Reconciliation

### Table Footer

The Phase 2 reset finding is visually confirmed.

```txt
Light:
  tfoot border-top: 3px solid rgb(29, 27, 32)

Dark:
  tfoot border-top: 3px solid rgb(230, 224, 233)

Visual result:
  Too strong in both modes.

Bucket:
  reset

Route:
  BACKLOG #41 table reset candidate.
```

Potential future direction noted during QA:

```txt
Footer surface:
  consider surface-container-ish treatment.

Footer border:
  consider outline-variant-ish treatment rather than currentcolor.
```

No value is chosen in v3.6.2.

### Table Header Rule Question

DevTools can show a source rule shaped like:

```css
.wp-block-table thead {
  border-bottom: 3px solid;
}
```

The actual computed specimen state is different:

```txt
thead:
  border-bottom: 0px none

th:
  border-bottom: 1px solid outline tone

Dark th evidence:
  background: rgb(43, 41, 48)       /* surface-container-high */
  border:     rgb(147, 143, 153)    /* outline */
```

Interpretation:

```txt
The visible header separator is the th cell border, not the thead 3px rule.
Axismundi/Pilot table reset rules override the thead border, then put the
visible line on table cells.
```

## Phase 3 Finding Catalog

| ID | Bucket | Evidence | Route |
|---|---:|---|---|
| editor-invalid-content | backlog | Opening the generated specimen wall in the block editor shows "Block contains unexpected or invalid content"; front-end rendering is normal. | Future #43 fixture/editor-compat refinement. |
| mark-element-missing | backlog | Current Tier 1 fixture does not include `<mark>` / Highlight. It may appear via Markdown or editor toolbar. | Add to follow-on specimen coverage. |
| table-footer-contrast | reset | `tfoot` remains 3px currentcolor in light/dark and reads too strong. | BACKLOG #41 table reset candidate. |
| button-anchor-semantics | semantic-decision | Button variants remain link-based core/button output; underline/user-select leakage persists across modes. | BACKLOG #41 button semantic decision. |
| search-styleguide-delta | bridge | Pilot core/search bridge differs from lab Search bar module specimen. | BACKLOG #41 search bridge comparison input. |
| code-long-line-overflow | bridge | `prose.html#code-blocks` already handles long one-line code with horizontal scroll; specimen only covers a short code line. | Add long-line code case to bridge/specimen input. |
| dropcap-present | no-action | Drop cap appears already applied in existing block/styleguide coverage. | No immediate action. |
| quote-pullquote-semantics | semantic-decision | core/quote uses `blockquote`; core/pullquote wraps `blockquote` inside `figure`, mixing quote styling and semantic concerns. | Future semantic review / possible Gutenberg upstream note. |
| separator-variant-visibility | bridge | Editor lists Default, wide line, dots, inset divider, middle inset divider; inset/middle inset look visually indistinct and dots are hard to see. | BACKLOG #41 separator bridge/style-variation input. |
| material-symbols-font-constraint | backlog | Material webfont implementation will constrain some visual/style decisions. | Future icon/font implementation note. |

## Phase 2 Classification Impact

The original 26-entry Phase 2 table remains valid:

```txt
Tier 1 entries classified: 26 / 26
Unclassified entries:      0
```

Phase 3 adds visual QA findings that either:

```txt
1. confirm an existing Phase 2 bucket:
   table-footer-contrast
   button-anchor-semantics

2. identify follow-on specimen coverage or #41 inputs:
   mark-element-missing
   search-styleguide-delta
   code-long-line-overflow
   quote-pullquote-semantics
   separator-variant-visibility

3. record environment/methodology constraints:
   editor-invalid-content
   material-symbols-font-constraint
```

No Phase 3 finding is promoted to a v3.6.2 blocker.

## Close Criteria

```txt
Light/dark specimen wall reviewed: yes
Phase 2 reset finding visually confirmed: yes
Phase 2 semantic-decision finding visually confirmed: yes
New findings routed, not patched: yes
```
