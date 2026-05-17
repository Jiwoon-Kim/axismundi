# List — Spec Audit (v3.5.11 Phase 5 Close)

> **Status**: Phase 5 close complete.  
> **Component**: List #33  
> **Category**: Component Full-Spec  
> **Audit shape**: 3-doc trio; no runtime audit at v3.5.11 entry.  
> **Baseline**: `components.css` §26 + `style-guide.html#components-list`

---

## §0 — Audit Status

Phase 5 release close is complete for the List SPEC audit.

Phase 5 verdict:

| Criterion | Phase 5 status | Notes |
|---|---|---|
| #1 M3 spec coverage | PASS | 1/2/3-line rows, standard/segmented styles, state matrix, selection modes, token-level List map |
| #2 Token-driven implementation | PASS | Baseline §26 token mismatch candidates resolved in-cycle; `corner-full` and v3.5.9 pill-stable untouched |
| #3 Pattern HTML completeness | PASS at Phase 2 | `lab-list.css` and `lab-list-pattern.html` created; no `lab-list.js` |
| #4 Audit doc completeness | PASS | SPEC/MEASUREMENT/WP-MAPPING authored as 3-doc trio |
| #5 Dependency declarations | PASS | ripple/icon-system/Avatar consumer-state declared |

---

## §1 — Inputs Read

- `docs/v3.5.11/LIST-PHASE-0-PLAN.md`
- `docs/v3.5.11/LIST-PHASE-0-REPORT.md`
- `docs/v3.5.0/MODULE-STATUS-MATRIX.md`
- `products/reference-implementations/axismundi-lab/stylesheets/components.css`
  §26
- `products/reference-implementations/axismundi-lab/style-guide.html`
  `#components-list`
- M3 list pages extracted through Playwright:
  - overview
  - specs
  - guidelines
  - accessibility
- Wave 1 precedents:
  - Button v3.5.1
  - Icon button v3.5.2
  - Card v3.5.3
  - FAB family v3.5.5
  - Text field v3.5.7
  - Search bar v3.5.8
  - Button group v3.5.10
- Ripple v2 v3.5.6 `data-ax-ripple` contract

---

## §2 — Component Identity

List is a Display-family Component Full-Spec surface.

It owns:

- list container visuals,
- row/item visual structure,
- 1-line, 2-line, and 3-line density,
- standard and segmented list styles,
- leading/trailing slot layout,
- selected/disabled visual states,
- row-level state-layer and shape morphing,
- row semantic guidance for static, action, navigation, and selectable cases.

It does not own:

- Avatar component closure,
- Menu behavior,
- Tabs behavior,
- Navigation component behavior,
- drag/reorder,
- virtualization,
- expandable row runtime,
- async data loading,
- selection persistence,
- nested multi-action runtime.

List is not just an HTML `ul` theme style. It is the M3 application-row
primitive used by settings, feeds, navigation-like collections, search results,
directories, and content rows.

---

## §3 — M3 Spec Digest

M3 list guidance relevant to Axismundi:

- List items are usually 56dp, 72dp, or 88dp tall depending on content.
- The tallest element determines row height.
- Standard and segmented lists are visual variants.
- Required structure: container + label text.
- Optional slots:
  - overline,
  - supporting text,
  - trailing text,
  - leading icon,
  - leading Avatar,
  - leading media,
  - trailing icon,
  - trailing icon button,
  - divider,
  - selection control.
- Leading and trailing slots are not automatically accessible; their contents
  must follow their own semantic contract.
- Selection lists use listbox/option, radio, or checkbox semantics depending on
  the interaction model.
- Keyboard guidance includes Tab, arrow-key movement, and Space/Enter
  activation for selectable/interactive rows.
- Selection must not be indicated by color alone.
- Ripple appears for tapped interactive list items.

Axismundi v3.5.11 interprets this as:

- static rows are allowed and do not get ripple,
- action/navigation rows are native controls/links and can get bounded ripple,
- selection patterns are documented, but no extracted list runtime is created
  in this cycle,
- Avatar/media/icon slots are composition points, not owned subcomponents.

---

## §4 — Baseline Contract

Current baseline §26 already implements the visual foundation:

| Surface | Baseline class | Current contract |
|---|---|---|
| Container | `.ax-list` | flex column, surface, corner-large |
| Segmented container | `.ax-list--segmented` | 2px gap, item-level surface |
| Row | `.ax-list__item` | 56px min-height, 16px inline padding |
| Two-line row | `.ax-list__item--two-line` | 72px min-height |
| Three-line row | `.ax-list__item--three-line` | 88px min-height |
| Leading slot | `.ax-list__leading` | icon/Avatar/image host |
| Content slot | `.ax-list__content` | overline/label/supporting column |
| Overline | `.ax-list__overline` | label-small |
| Label | `.ax-list__label` | body-large, single-line truncate |
| Supporting | `.ax-list__supporting` | body-medium, clamp in 3-line rows |
| Trailing slot | `.ax-list__trailing` | icon host |
| Trailing text | `.ax-list__trailing-text` | label-small |
| Divider | `.ax-list__divider` | 1px outline-variant separator |
| Selected | `.is-selected`, `[aria-selected=true]` | secondary-container fill |
| Disabled | `:disabled`, `[aria-disabled=true]` | 38% disabled treatment |

Baseline state machine:

- rest: corner-extra-small,
- hover: corner-medium,
- focus-visible/active: corner-large,
- selected: corner-large,
- disabled: corner-extra-small.

Phase 2 must consume this baseline exactly; it must not invent `.list-item`,
`.md-list`, or non-baseline class names.

---

## §5 — Variant Matrix

Phase 2 pattern scope should cover:

| Axis | Required variants |
|---|---|
| List style | standard, segmented |
| Row height | 1-line, 2-line, 3-line |
| Leading slot | none, icon, Avatar, image |
| Content slot | label only, overline + label, label + supporting, overline + label + supporting |
| Trailing slot | none, trailing text, trailing icon |
| State | rest, hover/focus-ready, selected, disabled |
| Semantics | static, action button, navigation anchor |
| Ripple | none for static, bounded for interactive |

Selection-control rows are documented but not required as live behavior in
v3.5.11 Phase 2. If included, they must be clearly marked as static catalog
specimens or plugin-managed behavior.

---

## §6 — Native Semantics Decision Tree

Canonical semantic patterns:

| Use case | Canonical markup | Rationale |
|---|---|---|
| Static editorial list | `ul` / `ol` / `li` | Native list semantics |
| Static app row group | `div role="list"` + noninteractive rows | Acceptable for non-HTML-list visual collections |
| Action row | `button type="button" class="ax-list__item"` | Native activation semantics |
| Navigation row | `a href class="ax-list__item"` | Native navigation semantics |
| Single selectable row | Phase 2 may show static `aria-selected`; full keyboard state is plugin/runtime territory |
| Multi-select row | Deferred; checkbox/listbox semantics require owner behavior |
| Multi-action row | Compose controls carefully; avoid nested interactive row button |

Current public style-guide specimens use `button role="listitem"`. Phase 1
does **not** accept that as the canonical pattern for interactive rows.

Decision:

- For action rows, keep native `button` semantics and do not override the role.
- For navigation rows, use `a href`.
- For list semantics around interactive rows, use surrounding descriptive
  structure/captions rather than forcing each native control to `role=listitem`.
- For selectable `listbox` patterns, a future runtime/selection owner must
  manage focus and state if true listbox behavior is required.

This preserves Principle 1 and Principle 2:

- visible control = real runtime control,
- native semantic element is used whenever available.

---

## §7 — Selected State Contract

Current baseline supports:

- `.is-selected`,
- `[aria-selected="true"]`.

Phase 2 may use selected visual specimens for:

- static catalog rows,
- action/navigation rows that represent current location or current choice,
- selectable rows when clearly marked as static demo state.

Important boundary:

`aria-selected` is not a magic visual-only attribute. If a specimen represents a
true selectable widget, the surrounding semantics must also make sense. Phase 2
should avoid implying a fully managed listbox if no JS/runtime is present.

Selection indication:

- selected background uses secondary-container,
- text/icons switch to on-secondary-container,
- selected shape uses corner-large,
- selected state must be paired with textual/structural context where color
  alone would be insufficient.

---

## §8 — Disabled State Contract

List follows Pattern A with two cases:

1. **Native disabled**:
   - use only when the row is a native `button`,
   - baseline selector: `.ax-list__item:disabled`.
2. **ARIA disabled / plugin-managed**:
   - use for anchors or custom managed rows that cannot be natively disabled,
   - baseline selector: `.ax-list__item[aria-disabled="true"]`,
   - plugin/integration must prevent activation and update focus behavior.

Static rows should not be called "disabled" unless they represent unavailable
content. A static row is simply non-interactive.

---

## §9 — Dependency Statement

| Dependency | Consumer state | Notes |
|---|---|---|
| `ripple/` | TARGET for interactive row items; NONE for static rows | Bounded row-level ripple only |
| `icon-system/` | CURRENT conditional | Leading/trailing icons only |
| Avatar #32 | RECORD composition dependency | Leading slot only; no fold-in |
| Button / Icon button | Composition-only | Trailing action controls if used |
| `components.css` §0 state-layer | CURRENT | Baseline Pattern A row state layer |
| `components.css` §26 | CURRENT | Source visual baseline |

Phase 5 updated Matrix row #33 and the Ripple sub-table after Phase 2/3
proved the bounded interactive-row implementation.

---

## §10 — Token-Level M3 Spec Map

Phase 3 review found that Phase 1's original List audit was not yet
"full-spec" at the token-table level. Baseline §26 is mostly token-driven, but
the audit body had not explicitly mapped M3 List token rows such as List Common,
Enabled, and Selected state colors.

Required M3 token map for List Common / Enabled:

| M3 token row | M3 token | Baseline §26 status |
|---|---|---|
| list item container color | `md.sys.color.surface` | Implemented via `.ax-list` background |
| list item segmented container color | `md.sys.color.surface` | Mismatch candidate: baseline segmented container is `transparent`; item surface is `surface` |
| label text color | `md.sys.color.on-surface` | Implemented through `.ax-list__item` color + inherited label |
| supporting text color | `md.sys.color.on-surface-variant` | Implemented |
| overline color | `md.sys.color.on-surface-variant` | Implemented |
| leading icon color | `md.sys.color.on-surface-variant` | Implemented |
| trailing icon color | `md.sys.color.on-surface-variant` | Implemented, but M3 also distinguishes unselected trailing icon below |
| unselected trailing icon color | `md.sys.color.on-surface` | Mismatch candidate: baseline trailing slot uses `on-surface-variant` |
| trailing supporting text color | `md.sys.color.on-surface-variant` | Implemented through `.ax-list__trailing-text` |
| leading avatar color | `md.sys.color.primary-container` | Composition dependency; Avatar #32 owns exact token |
| leading avatar label color | `md.sys.color.on-primary-container` | Composition dependency; Avatar #32 owns exact token |
| container elevation | `md.sys.elevation.level0` | Implemented as flat/no shadow; comment documents level0 |

Selected state:

| M3 token row | M3 token | Baseline §26 status |
|---|---|---|
| selected container color | `md.sys.color.secondary-container` | Implemented |
| selected text/icon color | `md.sys.color.on-secondary-container` | Implemented for leading/trailing/overline/supporting/trailing text |

Phase 3 finding:

```txt
P2 — Token-level List full-spec table was missing from initial Phase 1 audit.
Disposition: audit docs updated in-cycle. Baseline color mismatches are honest
findings for Phase 5 decision, not edited during Phase 3.
```

Phase 5 decision: Option A succeeded. The two List-specific color mismatches
were patched in `components.css` §26 in-cycle:

- segmented container color now resolves to `md.sys.color.surface`,
- unselected direct trailing icons now resolve to `md.sys.color.on-surface`.

Playwright also caught the direct-child icon rule overriding selected/disabled
state color inheritance. The selected and disabled direct-icon overrides were
added inside §26 so existing selected/disabled row semantics remain intact.
No BACKLOG #33 was opened.

---

## §11 — Anti-Patterns

Forbidden or discouraged:

- `div role="button"` for rows that could be native `button`.
- `button role="listitem"` as the canonical interactive row pattern.
- Nested buttons inside a row button.
- `data-ax-ripple` on `.ax-list` container.
- Ripple on static informational rows.
- Folding Avatar into List implementation.
- Treating `core/list` as the only WordPress mapping.
- Using List to implement Menu/Tabs/Nav semantics.
- Adding drag/reorder handles in v3.5.11.
- Presenting multi-select behavior without an owner for state/focus updates.
- Inventing `.ax-list__body` or other non-baseline slots.

---

## §12 — Phase 2 Pattern Expectations

Expected Phase 2 deliverables:

- `lab-list.css`
- `lab-list-pattern.html`

No `lab-list.js` is expected unless Phase 2 plan intentionally expands scope
and re-opens the runtime decision. Default v3.5.11 path is CSS + HTML catalog.

Pattern HTML should include:

- standard static list,
- segmented list,
- single-line action rows,
- two-line Avatar rows,
- three-line supporting text rows,
- navigation anchor row,
- selected row,
- native disabled button row,
- aria-disabled plugin-managed anchor row,
- divider specimen,
- leading/trailing icon specimens,
- bounded ripple interactive row specimen,
- static row without ripple.

Each behavior-looking specimen must include a user-facing caption when state is
static catalog state rather than live app behavior.

---

## §13 — G1-G10 Gate Readiness

| Gate | Phase 5 status | Notes |
|---|---|---|
| G1 validator | PASS | 1.000 / 1.000 / 1.000 / 1.000 |
| G2 baseline scope | PASS | Only `components.css` §26 changed, per Phase 5 plan Option A |
| G3 publish | N/A | Main publish surface not regenerated in this close |
| G4 artifacts | PASS | `lab-list.css` + `lab-list-pattern.html` |
| G5 CHANGELOG | PASS | v3.5.11 entry added |
| G6 Static Visual QA | PASS | User QA + Playwright geometry/color checks |
| G7 Principle 1 | PASS | Lab pattern uses real row surfaces; `button role=listitem` is not canonicalized |
| G8 Principle 2 | PASS | Native button/anchor/list semantics preferred |
| G9 WCAG accuracy | PASS | MEASUREMENT lists explicit SCs |
| G10 3-doc audit pattern | PASS at Phase 1 | SPEC/MEASUREMENT/WP-MAPPING authored |

---

## §14 — Verdict

Phase 5 SPEC verdict:

```txt
#1 M3 spec coverage             PASS
#2 Token-driven implementation  PASS
#3 Pattern HTML completeness    PASS
#4 Audit doc completeness       PASS
#5 Dependency declarations      PASS
```

List #33 is closed as Wave 1 #8. Phase 2/3 created lab-scoped CSS + pattern
HTML, preserved static/action/navigation row semantic splits, avoided canonical
`button role=listitem`, kept ripple item-only, and left runtime behavior
uncreated. Phase 5 additionally aligned the two small baseline §26 color gaps
found by token-level M3 review without expanding outside the List section.

---

## §15 — Cross-References

- `docs/v3.5.11/LIST-PHASE-0-REPORT.md`
- `LIST-MEASUREMENT-AUDIT.md`
- `LIST-WP-MAPPING.md`
- `docs/v3.5.0/MODULE-STATUS-MATRIX.md` row #33
- Ripple v2 v3.5.6
- Avatar #32 matrix record
- Button group v3.5.10 native semantics precedent
- Text field v3.5.7 3-doc native/CSS interaction precedent

---

## §16 — What This Audit Does NOT Do

This audit does not:

- create a runtime audit,
- create additional lab artifacts beyond `lab-list.css` and
  `lab-list-pattern.html`,
- create `lab-list.js`,
- edit baseline files outside `components.css` §26,
- close Avatar #32,
- implement multi-select behavior,
- implement listbox JS,
- implement drag/reorder,
- implement virtualization,
- implement Menu/Tabs/Nav,
- register WordPress block styles.

---

## §17 — v3.5.13 List Token Coverage Extension

This section is a v3.5.13 cleanup extension for BACKLOG #33. It does not
reopen the v3.5.11 List release; it records the additional M3 token rows
surfaced after v3.5.11 Phase 5 close.

Token-row classification:

| M3 token group | v3.5.13 classification | Notes |
|---|---|---|
| Enabled container / segmented container | Covered by §26 | `surface` after v3.5.11 patch |
| Label / supporting / overline colors | Covered by §26 | on-surface / on-surface-variant split |
| Leading / trailing icons | Covered by §26 | trailing direct icon split patched in v3.5.11 |
| Selected container/content | Covered by §26 | secondary-container + on-secondary-container |
| Disabled | Covered by §26 Pattern A | on-surface 10% / 38%; Phase 2 may verify selected-disabled nuance |
| Disabled-selected | Candidate comparison | Needs measurement check before patch claim |
| Hover / focus / pressed state-layer | Covered by generic §0 foundation | Component-specific token rows map to shared Pattern A |
| Focus indicator | Candidate comparison | Baseline uses 2px secondary / -3px; M3 dump says 3dp / -3dp |
| Dragged | Behavior-deferred | §0 has `[data-dragging]`; List drag/reorder runtime remains out of scope |
| Spacing | Covered by §26 | 16 / 10 / 12 / 2 values present |
| Shape | Partially covered | expressive state machine present; row-by-row confirmation needed |
| Size / typography | Mostly covered | 56/72/88, icon 24, image 56, text tokens present; video slot deferred |
| Leading avatar | Composition-owned | Avatar #32 record owns avatar primitive |

v3.5.13 disposition:

```txt
Audit-extension first.
Phase 2 may patch only narrow components.css §26 mismatches proven by
measurement. Broad §0 state-layer changes and drag/reorder runtime are out of
scope.
```

Phase 2/3 result:

```txt
Narrow §26 patches landed:
  - focus indicator thickness now matches the 3dp / -3dp row;
  - selected-disabled container resolves to a 38% on-surface mix;
  - segmented lists keep the wrapper transparent while item containers own
    `surface`; the 2dp gap reveals the surrounding surface rather than a
    component-owned wrapper color;
  - expanded parent rows expose the M3 List Expand trailing icon container
    color through the existing `surface-container` token (#211f26 in dark
    scheme);
  - trailing supporting time text is locked to a single line.

No generic §0 state-layer rewrite, drag/reorder runtime, or Avatar primitive
change was introduced.
```

BACKLOG #33 is closed in v3.5.13 Phase 5.
