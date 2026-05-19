# v3.6.2 — WP Core Block Specimen Wall — Phase 2 Classification

Date: 2026-05-20

## Verdict

Phase 2 is complete.

The specimen wall now produces per-block computed snapshots for all Tier 1
families and classifies every captured entry into exactly one bucket.

This remains an evidence cycle. No bridge, reset, component baseline, or token
architecture implementation changes were made.

## Snapshot Source

```txt
Command:
  npm run validate:specimen-wall

Raw output:
  tmp/phase1-specimen-wall/specimen-wall-render-gate.json
  tmp/phase1-specimen-wall/specimen-wall-390.png

Coverage:
  Tier 1 block families represented: 11 / 11
  Tier 1 entries classified:         26 / 26
  Unclassified entries:               0
```

## Bucket Totals

```txt
no-action:          20
reset:               1
bridge:              0
semantic-decision:   5
backlog:             0

Total:              26
```

`no-action` is intentionally used. The wall is an evidence map, not an automatic
todo generator.

## Classification Table

| ID | Family | Bucket | Evidence | Route |
|---|---|---:|---|---|
| paragraph-default | core/paragraph | no-action | Transparent background, no native border, on-surface text. | No change. |
| heading-h1 | core/heading | no-action | Transparent background, no native border, typography renders normally. | No change. |
| heading-h2 | core/heading | no-action | Transparent background, no native border, typography renders normally. | No change. |
| heading-h3 | core/heading | no-action | Transparent background, no native border, typography renders normally. | No change. |
| list-unordered | core/list | no-action | Standard list marker/indent preserved; no conflicting native surface. | No change. |
| list-ordered | core/list | no-action | Standard ordered list marker/indent preserved; no conflicting native surface. | No change. |
| list-segmented | core/list | no-action | Existing segmented list bridge renders as flex with no native border leakage. | No change. |
| quote-default | core/quote | no-action | Quote uses secondary text color and expected padding; no raw native border conflict in snapshot. | No change. |
| code-block | core/code | no-action | Code block uses visible token surface `rgb(243, 237, 247)` with no native border. | No change. |
| table-default | core/table | no-action | Wrapper border resolves to M3 outline tone `rgb(202, 196, 208)`; prior table reset holds. | No change. |
| table-stripes | core/table | no-action | Wrapper border resolves to M3 outline tone; stripes bridge remains stable. | No change. |
| table-footer | core/table | reset | `tfoot` keeps `border-top-width: 3px` and `border-top-color: rgb(29, 27, 32)`. | BACKLOG #41 input: table reset candidate. |
| button-fill | core/buttons + core/button | semantic-decision | Renders as `<a>` with `text-decoration-line: underline` and `user-select: auto`. | BACKLOG #41 input: button semantic boundary. |
| button-outline | core/buttons + core/button | semantic-decision | Renders as `<a>` with underline/user-select leakage despite correct M3 outline surface. | BACKLOG #41 input: button semantic boundary. |
| button-tonal | core/buttons + core/button | semantic-decision | Renders as `<a>` with underline/user-select leakage despite correct tonal surface. | BACKLOG #41 input: button semantic boundary. |
| button-elevated | core/buttons + core/button | semantic-decision | Renders as `<a>` with underline/user-select leakage despite correct elevated surface. | BACKLOG #41 input: button semantic boundary. |
| button-text | core/buttons + core/button | semantic-decision | Renders as `<a>` with underline/user-select leakage; needs link-compatible vs button semantics decision. | BACKLOG #41 input: button semantic boundary. |
| search-default | core/search | no-action | Default search form is transparent with no native border conflict. | No change. |
| search-filled | core/search | no-action | Filled search bridge renders token surface and elevation; no native border conflict. | No change. |
| separator-default | core/separator | no-action | Separator resolves to M3 outline tone surface. | No change. |
| separator-inset | core/separator | no-action | Inset separator resolves to M3 outline tone surface. | No change. |
| separator-middle-inset | core/separator | no-action | Middle inset separator resolves to M3 outline tone surface. | No change. |
| group-card-filled | core/group | no-action | Card-filled surface resolves to M3 surface-container-high tone. | No change. |
| group-card-elevated | core/group | no-action | Card-elevated surface and elevation render as expected. | No change. |
| group-card-outlined | core/group | no-action | Card-outlined surface renders without native border conflict in this snapshot. | No change. |
| columns-default | core/columns + core/column | no-action | Layout wrapper renders as flex with no native visual conflict. | No change. |

## Seed Finding Reframe

### v3.6.1 Finding 1 — Table Footer Border

```txt
Previous route:
  P3 non-blocking finding routed to BACKLOG #43.

Specimen evidence:
  table-footer entry captures tfoot border-top-width: 3px
  table-footer entry captures tfoot border-top-color: rgb(29, 27, 32)

Phase 2 bucket:
  reset

Next route:
  BACKLOG #41 table reset candidate.
```

### v3.6.1 Finding 2 — Core Button Semantic Boundary

```txt
Previous route:
  P3 non-blocking finding routed to BACKLOG #43.

Specimen evidence:
  all five button variants render as <a>
  all five show text-decoration-line: underline
  all five show user-select: auto

Phase 2 bucket:
  semantic-decision

Next route:
  BACKLOG #41 button semantic decision:
    core/buttons style extension vs custom block vs link-compatible affordance.
```

## Phase 2 Close Criteria

```txt
Tier 1 block families covered: 11 / 11
Tier 1 entries classified:     26 / 26
Unclassified entries:          0
no-action bucket used:         yes
Seed findings bucketed:        yes
```

## Non-Goals Preserved

```txt
- No bridge/reset CSS patched.
- No button semantic decision made.
- No custom blocks added.
- No Tier 2/3 coverage claim made.
- No expected M3 mapping assertions added.
```
