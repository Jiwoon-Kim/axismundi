# Axismundi v3.5.11 — Wave 1 — List #33 Phase 0 Report

> **Status**: Phase 0 complete — approved for Phase 1 planning  
> **Component**: List #33  
> **Category**: Component Full-Spec  
> **Cycle**: v3.5.11  
> **Date**: 2026-05-17  
> **Scope**: Documentation-only inventory and ontology decisions. No baseline,
> lab module, release, state, or handoff edits.

---

## §0 — Phase 0 Verdict

List #33 should enter the standard Wave 1 Component Full-Spec path.

Phase 0 locks:

- **Audit shape**: 3-doc trio.
  - `LIST-SPEC-AUDIT.md`
  - `LIST-MEASUREMENT-AUDIT.md`
  - `LIST-WP-MAPPING.md`
- **No `LIST-RUNTIME-AUDIT.md` at v3.5.11 entry**.
  - Current baseline behavior is native button/anchor/list markup plus CSS state
    transitions.
  - No extracted reusable JavaScript runtime currently exists for List.
  - A runtime audit becomes appropriate only if a future phase introduces
    roving tabindex, listbox state management, expandable rows, drag/reorder,
    virtualization, or another extracted interaction layer.
- **Avatar #32 remains a Baseline-only Record**.
  - List composes Avatar through the leading slot.
  - List does not fold Avatar into this Component Full-Spec cycle.
- **Ripple state**: split by item kind.
  - Static/informational rows: `ripple/ = NONE`.
  - Interactive action/navigation/selectable rows: `ripple/ = TARGET`, bounded
    to the row/item surface, pending Phase 2 implementation verification.
- **Icon system state**: `icon-system/ = CURRENT conditional`.
  - Leading/trailing icons consume the icon system when present.
  - Avatar/image/text-only rows do not require icon-system.
- **M3 Expressive guidance is acknowledged**.
  - Standard and segmented list visuals are in scope.
  - Behavior-heavy patterns stay out of scope unless a future runtime cycle is
    explicitly opened.

---

## §1 — Authoritative Inputs

### Local Inputs

- `docs/v3.5.11/LIST-PHASE-0-PLAN.md`
- `docs/v3.5.0/MODULE-STATUS-MATRIX.md`
- `products/reference-implementations/axismundi-lab/stylesheets/components.css`
  §26 List
- `products/reference-implementations/axismundi-lab/style-guide.html`
  `#components-list`
- Completed Wave 1 precedents:
  - Button v3.5.1
  - Icon button v3.5.2
  - Card v3.5.3
  - FAB family v3.5.5
  - Text field v3.5.7
  - Search bar v3.5.8
  - Button group v3.5.10
- Ripple v2 v3.5.6 contract and `data-ax-ripple` precedent

### External M3 Inputs

The M3 list pages are JavaScript-rendered. Phase 0 used Playwright extraction as
the same fallback pattern established during Button group v3.5.10.

- `https://m3.material.io/components/lists/overview`
- `https://m3.material.io/components/lists/specs`
- `https://m3.material.io/components/lists/guidelines`
- `https://m3.material.io/components/lists/accessibility`

Extracted M3 facts used by this report:

- Lists help people find a specific item and act on it.
- M3 Expressive lists add segmented visual styling, selection treatment, and
  slot guidance; baseline lists remain valid.
- List item height is driven by the tallest element: 56dp, 72dp, or 88dp.
- Most list content is middle-aligned; 88dp or 3+ line items are top-aligned.
- Standard and segmented styles are visual choices, not separate behavior
  contracts.
- Required slots: container and label text.
- Optional slots include overline, supporting text, trailing text, leading
  avatar/icon/media, trailing icon/icon button, divider, and selection controls.
- Slot contents are not accessible by default; slotted interactive elements
  must follow their own interaction rules.
- Selection list semantics include listbox/option or radio/checkbox patterns.
- Keyboard guidance includes Tab to the first/selected item, arrow-key movement
  across items, and Space/Enter activation.

---

## §2 — Baseline Inventory

### `components.css` §26

Baseline class surface:

- `.ax-list`
- `.ax-list.ax-list--segmented`
- `.ax-list__item`
- `.ax-list__item--two-line`
- `.ax-list__item--three-line`
- `.ax-list__leading`
- `.ax-list__leading-image`
- `.ax-list__content`
- `.ax-list__overline`
- `.ax-list__label`
- `.ax-list__supporting`
- `.ax-list__trailing`
- `.ax-list__trailing-text`
- `.ax-list__divider`
- `.ax-list__item.is-selected`
- `.ax-list__item[aria-selected="true"]`
- `.ax-list__item:disabled`
- `.ax-list__item[aria-disabled="true"]`

Implemented baseline dimensions and structure:

- Container: surface color, level0, corner-large.
- Standard list: flat list container.
- Segmented list: `2px` gap, transparent container, per-item surface and
  corner-large.
- Item heights:
  - single-line: `min-height: 56px`
  - two-line: `min-height: 72px`
  - three-line: `min-height: 88px`
- Item horizontal padding: `16px`.
- Leading/trailing content gap: `12px`.
- Icon slot: `24px`.
- Leading image slot: `56px` square.
- Avatar slot: composed via `.ax-avatar`, not owned by List.
- Content: overline, label, supporting text.
- Three-line supporting text: two-line clamp.
- State layer: Pattern A `::before`.
- Shape state machine:
  - rest: corner-extra-small
  - hover: corner-medium
  - focus/active/selected: corner-large
  - disabled: corner-extra-small
- Selected state: `.is-selected` or `[aria-selected="true"]`.
- Disabled state: native `:disabled` or `[aria-disabled="true"]`.

Deferred baseline comments already present:

- Video slot.
- Multi-select differentiated visuals.
- Drag handle slot.

### `style-guide.html` `#components-list`

Public specimens currently include:

- Standard single-line list.
- Selected single-line row.
- Divider rows.
- Two-line rows with Avatar leading slot.
- Three-line row with overline, label, supporting text.
- Segmented list.
- Disabled segmented item.

Important Phase 0 observation:

The current style guide uses `role="list"` on the container and
`button role="listitem"` on interactive rows. That is useful evidence of
baseline intent, but it needs Phase 1 semantic review because overriding a
native button with `role="listitem"` can obscure button name/role/value
semantics. Phase 1 SPEC must decide the canonical semantics matrix rather than
rubber-stamping the existing markup.

---

## §3 — Public Specimen Inventory

The current public style guide proves that List already has a visual baseline,
but not a full audit contract.

Specimen coverage:

| Specimen | Present | Notes |
|---|:---:|---|
| Standard list | Yes | Container + 3 rows |
| Single-line row | Yes | 56px baseline |
| Two-line row | Yes | Uses Avatar leading slot |
| Three-line row | Yes | Overline + label + supporting |
| Segmented list | Yes | Gap + per-item rounding |
| Selected state | Yes | `.is-selected` + `aria-selected=true` |
| Disabled state | Yes | Disabled row in segmented specimen |
| Leading icon | Yes | Inline SVG in current guide |
| Leading Avatar | Yes | Composed `.ax-avatar` |
| Trailing icon | Yes | Inline SVG |
| Trailing text | Yes | Keyboard shortcut text |
| Divider | Yes | `.ax-list__divider` |
| Static non-interactive rows | Partial | Current guide mostly uses buttons |
| Navigation rows | Not explicit | Anchor reset exists in CSS |
| Multi-action rows | Not explicit | Out of v3.5.11 primitive scope |
| Checkbox/radio/switch selection controls | Not explicit | Future behavior/selection cycle |

---

## §4 — Category And Audit Shape Decision

### Decision

List #33 uses the **3-doc Component Full-Spec trio**:

1. `LIST-SPEC-AUDIT.md`
2. `LIST-MEASUREMENT-AUDIT.md`
3. `LIST-WP-MAPPING.md`

No `LIST-RUNTIME-AUDIT.md` is created in Phase 1.

### Rationale

The Text field v3.5.7 rule remains valid:

> Extracted runtime present → consider 4-doc. Native/CSS interaction only →
> 3-doc, with interaction requirements inside SPEC.

For List v3.5.11:

- Current baseline is CSS + native interactive elements.
- No `lab/modules/list/` exists yet.
- No existing `list-runtime` or equivalent extracted JS module exists.
- M3 accessibility guidance does include keyboard/listbox behavior, but that
  is a semantic requirement first. It does not automatically require an
  extracted runtime in Axismundi.

Phase 1 SPEC must still cover:

- Static list semantics.
- Action row semantics.
- Navigation row semantics.
- Selectable row semantics.
- Listbox/option versus native button/anchor tradeoffs.
- Keyboard guidance and where native behavior is sufficient.

Runtime audit is deferred unless a later phase intentionally introduces:

- roving tabindex,
- managed `aria-activedescendant`,
- listbox state machine JS,
- expand/collapse rows,
- drag/reorder,
- virtualized/infinite list behavior,
- or a reusable multi-action list runtime.

---

## §5 — Dependency Profile

### Ripple

Consumer-state split:

| Surface | ripple state | Phase 0 decision |
|---|---|---|
| Static/informational row | NONE | No action target; state layer/ripple not needed |
| Action row (`button`) | TARGET | Bounded ripple per interactive row |
| Navigation row (`a`) | TARGET | Bounded ripple per link row |
| Selectable row | TARGET if interactive | Row-level or control-level decision in Phase 1 |
| List container | NONE | Container is not the action surface |
| Divider | NONE | Non-interactive separator |

`MODULE-STATUS-MATRIX.md` currently lists List #33 item hover/action surface as
Ripple CANDIDATE. Phase 0 recommends promotion to TARGET for interactive rows,
with the actual matrix update reserved for Phase 5 after Phase 2/3 validation.

### Icon System

`icon-system/ = CURRENT conditional`.

List consumes icon-system only when a leading/trailing icon is present.
Text-only, Avatar, or image rows do not require icon-system.

### Avatar

`Avatar #32 = Baseline-only Record composition dependency`.

List uses Avatar in the leading slot but does not own Avatar sizing, shape,
record status, or closure.

### Button / Icon Button

Trailing icon buttons and multi-action rows are composition cases.

Phase 0 does not require them for primitive List closure. If Phase 2 includes a
trailing action specimen, it must route interaction to the composed Button/Icon
button consumer, not to a List-owned custom control.

---

## §6 — Variant And Slot Scope

In scope for v3.5.11:

- Standard list.
- Segmented list.
- 1-line, 2-line, and 3-line rows.
- Leading icon.
- Leading Avatar.
- Leading image.
- Overline.
- Label.
- Supporting text.
- Trailing text.
- Trailing icon.
- Divider.
- Selected state.
- Disabled state.
- Static rows.
- Action rows.
- Navigation rows.

Explicitly deferred:

- Video slot.
- Drag handle slot.
- Reorder behavior.
- Expand/collapse rows.
- Virtualized/infinite list behavior.
- Multi-select differentiated visuals beyond static documentation.
- Checkbox/radio/switch selection-control behavior.
- Menu, Tabs, Nav bar, Nav rail, and Toolbar ownership.
- Full Avatar record closure.

---

## §7 — Native Semantics Decision Tree

Phase 1 must formalize this decision tree:

| Use case | Preferred primitive | Notes |
|---|---|---|
| Static content list | `ul/ol/li` or `div role="list"` | No action target |
| Action row | `button type="button"` | Principle 2 native control |
| Navigation row | `a href` | Real navigation should be anchor |
| Single selectable list | TBD: listbox/option, radio, or native controls | Requires Phase 1 semantic decision |
| Multi selectable list | TBD: checkbox/listbox pattern | Likely future selection cycle |
| Multi-action row | Row + composed controls | Avoid nested ambiguous controls |

Phase 1 should not blindly preserve `button role="listitem"` from the current
style guide. It should decide whether that pattern is acceptable, needs
replacement, or should be limited to visual demo context.

Forbidden by default:

- `div role="button"` rows when a real `button` or `a` is appropriate.
- Interactive rows that expose only `role="listitem"` without action semantics.
- Nested buttons inside row buttons.
- Applying `data-ax-ripple` to the list container instead of the item.
- Treating Avatar as part of List implementation.

---

## §8 — WordPress Mapping Stub

Likely WordPress mapping candidates:

- `core/list` for static editorial lists.
- `core/navigation` or navigation-like patterns for link rows.
- `core/query` / `core/post-template` / latest-posts-like layouts for content
  collections that visually resemble lists.
- Comments, search results, profile directories, notification feeds, and
  settings panels as pattern/plugin territory.

Theme can provide:

- visual row styling,
- static/segmented list patterns,
- icon/avatar/image slot styling,
- dividers,
- selected/disabled visual states,
- pattern snippets.

Plugin should own:

- dynamic data loading,
- search/filter/pagination,
- selection state persistence,
- async row actions,
- drag/reorder,
- virtualized/infinite lists,
- ARIA state updates for complex selectable lists.

Phase 1 WP-MAPPING must avoid overclaiming `core/list` as the only List route.
M3 List covers application/content rows, not only HTML bullet lists.

---

## §9 — Risks And Dispositions

### Risk 1 — 3-doc vs 4-doc Drift

**Risk**: M3 list accessibility guidance mentions listbox roles and keyboard
movement, which can tempt the cycle into a runtime audit.

**Disposition**: 3-doc trio is locked for Phase 1. Runtime audit is deferred
unless implementation introduces extracted JS behavior.

### Risk 2 — Avatar Fold-In

**Risk**: Because two-line specimens use Avatar leading slots, List could
accidentally absorb Avatar #32.

**Disposition**: Avatar remains Baseline-only Record. List documents Avatar as
a composition dependency only.

### Risk 3 — Ripple Over-Application

**Risk**: Applying ripple to every row or the container would incorrectly turn
static information into action surfaces.

**Disposition**: Static rows NONE; interactive rows TARGET; container NONE.

### Risk 4 — Neighbor Component Drift

**Risk**: List could drift into Menu, Tabs, Nav, Toolbar, or selection-control
ownership.

**Disposition**: Those are out of scope. List only owns primitive row/list
visual and semantic contract.

### Risk 5 — Current Style-Guide Semantics

**Risk**: `button role="listitem"` in current public specimens may hide native
button semantics.

**Disposition**: Phase 1 SPEC must audit and decide the canonical pattern.
Phase 0 records this as an honest baseline finding.

### Risk 6 — WordPress Mapping Overclaim

**Risk**: Mapping List only to `core/list` would ignore app/feed/settings
surfaces.

**Disposition**: WP-MAPPING must distinguish static editorial lists, theme
patterns, and plugin-owned dynamic list behavior.

### Risk 7 — Long Content Overflow

**Risk**: labels, supporting text, trailing text, and Avatar/image combinations
can overflow narrow viewports.

**Disposition**: Phase 2/3 must include Playwright viewport checks for
single-line truncation, three-line clamp, and trailing slot stability.

### Risk 8 — Multi-Action Nested Controls

**Risk**: M3 allows multi-action list items, but naive markup can produce nested
interactive controls.

**Disposition**: Primitive v3.5.11 may show composition only if it preserves
native semantics. Full multi-action behavior remains plugin/runtime territory.

---

## §10 — Phase 1 Entry Conditions

Phase 1 may begin when this report is approved.

Create:

- `products/reference-implementations/axismundi-lab/modules/list/docs/LIST-SPEC-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/list/docs/LIST-MEASUREMENT-AUDIT.md`
- `products/reference-implementations/axismundi-lab/modules/list/docs/LIST-WP-MAPPING.md`

Do not create in Phase 1:

- `LIST-RUNTIME-AUDIT.md`
- `lab-list.css`
- `lab-list.js`
- `lab-list-pattern.html`

Phase 1 must lock:

- semantics decision tree,
- style-guide `role=listitem` disposition,
- ripple TARGET/NONE split,
- Avatar composition boundary,
- icon-system conditional dependency,
- WP mapping boundaries,
- WCAG SC citation list,
- Phase 2 pattern HTML scope.

---

## §11 — G1-G10 Applicability

| Gate | Applicability | Phase 0 note |
|---|---|---|
| G1 Validator | Applies | Must remain 1.000 PASS throughout |
| G2 Baseline untouched | Applies | Phase 0 did not edit baseline |
| G3 Publish | Phase 5 | Only if publish surface changes |
| G4 Module artifacts | Phase 2 | `lab-list.css` + pattern HTML expected later |
| G5 CHANGELOG | Phase 5 | v3.5.11 close |
| G6 Static Visual QA | Phase 3 | Playwright + user QA |
| G7 Principle 1 | Applies | Visible runtime must match real semantic control |
| G8 Principle 2 | Applies | Prefer native `button`/`a`/list semantics |
| G9 WCAG SC accuracy | Applies | Phase 1 MEASUREMENT must cite SCs explicitly |
| G10 3-doc audit pattern | Applies | SPEC + MEASUREMENT + WP-MAPPING |

Likely WCAG SCs for Phase 1:

- SC 1.3.1 Info and Relationships.
- SC 1.4.3 Contrast Minimum.
- SC 1.4.11 Non-text Contrast.
- SC 2.1.1 Keyboard.
- SC 2.4.3 Focus Order.
- SC 2.4.7 Focus Visible.
- SC 2.5.8 Target Size (Minimum).
- SC 2.5.5 Target Size (Enhanced), with item-height nuance.
- SC 4.1.2 Name, Role, Value.
- SC 4.1.3 Status Messages, only if dynamic updates are introduced by a
  plugin/runtime.

---

## §12 — Non-Goals

This Phase 0 does not:

- edit `components.css`,
- edit `tokens.css`,
- edit `style-guide.html`,
- edit `blocks.css`,
- edit `theme.json`,
- create `lab/modules/list/`,
- close Avatar #32,
- create a runtime audit,
- wire ripple,
- implement listbox JavaScript,
- implement drag/reorder,
- implement expand/collapse,
- implement virtualized lists,
- implement Menu/Tabs/Nav behavior,
- register WordPress block styles,
- edit CHANGELOG/ROADMAP/MATRIX/BACKLOG,
- edit CURRENT-STATE or NEXT-SESSION.

---

## §13 — Self-Check

```txt
List #33 matrix row referenced             yes
3-doc trio decision                        yes
LIST-RUNTIME-AUDIT omitted                 yes
Avatar #32 fold-in forbidden               yes
ripple split TARGET/NONE recorded          yes
icon-system conditional recorded           yes
style-guide semantics risk recorded        yes
WP mapping stub included                   yes
G1-G10 applicability included              yes
non-goals included                         yes
baseline/lab/state/release edit required   no
```

---

## §14 — Final Verdict

Phase 0 is complete.

List #33 may proceed to Phase 1 with a 3-doc audit trio. The most important
Phase 1 work is semantic precision: List must distinguish static rows, action
rows, navigation rows, and selectable rows without collapsing them into one
generic `role=listitem` pattern. Ripple, Avatar, and icon-system dependencies
are now state-aware enough for Phase 1 audit authoring.

