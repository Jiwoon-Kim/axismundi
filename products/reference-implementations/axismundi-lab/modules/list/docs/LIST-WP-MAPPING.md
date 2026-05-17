# List — WordPress Mapping Audit (v3.5.11 Phase 5 Close)

> **Status**: Phase 5 close complete. No WordPress registration in this phase.  
> **Component**: List #33  
> **Companion docs**: `LIST-SPEC-AUDIT.md`, `LIST-MEASUREMENT-AUDIT.md`

---

## §0 — Mapping Framing

List has broader WordPress mapping than `core/list`.

M3 List covers application/content rows, settings rows, feeds, profile
directories, search results, notification rows, and navigation-like row
collections. WordPress has several nearby surfaces, none of which fully own the
entire M3 List contract.

Phase 5 verdict:

- theme can provide visual row/list patterns,
- plugin should own dynamic data, selection persistence, filtering, async
  actions, drag/reorder, virtualization, and complex ARIA state updates,
- `core/list` is valid only for static editorial lists.

---

## §1 — Inputs Read

- `docs/v3.5.11/LIST-PHASE-0-REPORT.md`
- `LIST-SPEC-AUDIT.md`
- `LIST-MEASUREMENT-AUDIT.md`
- `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md` principles via established Wave 1
  precedent
- `docs/v3.5.0/MODULE-STATUS-MATRIX.md`
- WordPress mapping precedents:
  - Button group v3.5.10
  - Text field v3.5.7
  - Card v3.5.3
  - Search bar v3.5.8

---

## §2 — WordPress Surface Inventory

| WordPress surface | Relationship to List | Verdict |
|---|---|---|
| `core/list` | Static editorial bullet/numbered lists | Partial mapping only |
| `core/navigation` | Link row collections | Adjacent; Nav owns nav semantics |
| `core/query` / `core/post-template` | Content rows/cards/feed lists | Pattern composition route |
| Latest posts/categories/comments patterns | List-like rows | Theme pattern or plugin route |
| Search results | List-like result rows | Search/plugin territory |
| Settings panels | App rows | Pattern/plugin territory |
| Profile/member directories | Avatar-leading rows | Plugin data + theme visuals |
| Notification feeds | Dynamic rows | Plugin territory |
| Menus | Similar row visuals | Menu component owns menu semantics |

---

## §3 — Mapping Paths

### Path A — Static Editorial List

Use `core/list` for editorial content.

Appropriate when:

- content is static,
- list semantics are genuinely HTML list semantics,
- no row action/navigation is implied,
- no selection state is required.

Not appropriate when:

- rows are app actions,
- rows are navigation links,
- rows are selected/disabled controls,
- rows need leading/trailing slot layout beyond editorial text.

### Path B — Theme Pattern Composition

Use theme patterns for app-like rows:

- settings list,
- profile directory,
- notification preview,
- search result row,
- account/menu-like grouped rows that are not ARIA menus.

The theme can supply `.ax-list` markup and visual structure, but data and
behavior remain external.

### Path C — Custom Block / Plugin Territory

Use plugin/custom block when:

- rows are dynamic,
- selection persists,
- filters/search/pagination update results,
- row actions call APIs,
- drag/reorder exists,
- virtualization/infinite scroll exists,
- complex ARIA state must be maintained.

---

## §4 — Theme-Can / Plugin-Should Boundary

Theme can:

- style static `.ax-list` patterns,
- expose standard/segmented list snippets,
- style leading/trailing icon/image/avatar slots,
- provide selected/disabled visual classes,
- provide divider patterns,
- document static catalog examples.

Plugin should:

- load row data,
- own query/filter/search state,
- own listbox selection state,
- update `aria-selected` / `aria-activedescendant` when applicable,
- prevent activation for `aria-disabled` rows,
- handle async row actions,
- implement drag/reorder,
- implement virtualization.

---

## §5 — Accessible Name / Role Contract

Static rows:

- should expose real list semantics when they are lists,
- should not pretend to be buttons.

Action rows:

- should be native buttons,
- must have an accessible name from `.ax-list__label` or explicit aria-label,
- must not override role to `listitem` as the canonical contract.

Navigation rows:

- should be anchors with `href`,
- must have link text/accessibility name,
- may use `.ax-list__item` styling.

Selectable rows:

- require a clear semantic owner:
  - listbox/option,
  - radio,
  - checkbox,
  - or plugin-owned state management.
- must not rely on `.is-selected` color alone.

---

## §6 — Anti-Patterns

Anti-patterns:

- Mapping every List surface to `core/list`.
- Using `core/list` for app action rows.
- Styling a `div` as a clickable row without native action semantics.
- Using `button role="listitem"` as the canonical action-row pattern.
- Nesting a button inside a row button.
- Applying `data-ax-ripple` to the list container.
- Treating Avatar as part of List ownership.
- Using List as Menu/Tabs/Nav replacement.
- Presenting dynamic selection without plugin/runtime state management.
- Using color alone to indicate selected row.
- Forgetting accessible names for icon-only trailing actions.
- Hiding disabled behavior behind `aria-disabled` without preventing activation.

---

## §7 — Core Block Inventory

| Core block / surface | Can map? | Notes |
|---|---|---|
| `core/list` | Yes, static only | Editorial list content |
| `core/navigation` | Not directly | Nav component owns navigation semantics |
| `core/query` | Pattern route | Query results can be styled as list rows |
| `core/post-template` | Pattern route | Repeated content row surfaces |
| `core/comments` / comments templates | Pattern route | Dynamic comments need plugin/theme care |
| `core/search` | Adjacent | Search results rows, not search input |
| `core/buttons` | No | Button group/action family owns buttons |
| `core/group` | Pattern wrapper | Can host visual list pattern |

---

## §8 — Phase 2 Pattern Recommendation

Phase 2 included WordPress-aware specimens, but did not register block styles:

- static editorial list specimen,
- app settings list specimen,
- profile directory row with Avatar leading slot,
- search-result style row,
- navigation anchor row,
- disabled/plugin-managed row,
- selected row with explanatory caption.

Each specimen should clarify whether it represents:

- static theme markup,
- native action/navigation,
- plugin-managed state,
- or future behavior.

---

## §9 — WordPress Mapping Verdict

Phase 5 WP mapping verdict:

```txt
core/list static mapping             PARTIAL
theme pattern route                  PASS
plugin territory boundary            PASS
accessible name/role contract        PASS
anti-pattern inventory               PASS
block registration                   DEFERRED
```

No WordPress baseline registration happened in v3.5.11. List closes as a lab
Component Full-Spec surface; `core/list` remains a partial static-editorial
mapping while dynamic application rows stay theme-pattern or plugin territory.

---

## §10 — Cross-References

- `LIST-SPEC-AUDIT.md`
- `LIST-MEASUREMENT-AUDIT.md`
- `docs/v3.5.11/LIST-PHASE-0-REPORT.md`
- Button group v3.5.10 WP mapping precedent
- Text field v3.5.7 theme-can/plugin-should precedent
- Card v3.5.3 pattern composition precedent
