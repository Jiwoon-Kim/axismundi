# Axismundi v3.5.11 — List #33 Phase 5 Plan

> **Status**: Plan-only. Awaiting approval before Phase 5 close.  
> **Component**: List #33  
> **Decision**: Option A — in-cycle baseline patch, with strict fallback trigger.  
> **Date**: 2026-05-17

---

## §0 — Plan Verdict

Phase 5 should close List #33 with a small in-cycle baseline color alignment
patch.

Patch scope is intentionally narrow:

1. `components.css` §26 List segmented container color:
   - `transparent` -> `var(--md-sys-color-surface)`
2. `components.css` §26 trailing icon split:
   - generic trailing slot remains `on-surface-variant`
   - direct trailing icon child gets `on-surface`

If either change expands outside List §26 or causes visible/Wave 1 regression,
stop and switch to Option B:

```txt
BACKLOG #33 — List baseline color alignment
```

---

## §1 — Why Option A

Option A is preferred because:

- mismatch count is two,
- both are List-specific,
- neither is a cross-cutting token graph issue,
- audit docs already identify the exact mismatch,
- keeping audit + baseline aligned in v3.5.11 gives a cleaner release story,
- expected diff is 2-4 CSS lines inside `components.css` §26 only.

---

## §2 — Baseline Diff Scope

Allowed file:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
```

Allowed region:

```txt
§26 List only
approx. lines 4105-4286
```

### §2.1 — Segmented Container Color

Before:

```css
.ax-list.ax-list--segmented {
  gap: 2px;
  background-color: transparent;
}
```

After:

```css
.ax-list.ax-list--segmented {
  gap: 2px;
  background-color: var(--md-sys-color-surface);
}
```

Rationale:

- M3 token row: List list item segmented container color =
  `md.sys.color.surface`.
- Child list items already use `surface`.
- This keeps segmented list background within the same List color family.

### §2.2 — Unselected Trailing Icon Color Split

Before:

```css
.ax-list__trailing {
  color: var(--md-sys-color-on-surface-variant);
}
.ax-list__trailing > svg,
.ax-list__trailing > .ax-icon {
  width: 24px;
  height: 24px;
  fill: currentColor;
}
```

After:

```css
.ax-list__trailing {
  color: var(--md-sys-color-on-surface-variant);
}
.ax-list__trailing > svg,
.ax-list__trailing > .ax-icon {
  width: 24px;
  height: 24px;
  color: var(--md-sys-color-on-surface);
  fill: currentColor;
}
```

Rationale:

- M3 distinguishes:
  - generic trailing/supporting text = `on-surface-variant`,
  - unselected trailing icon = `on-surface`.
- Selected-state override already recolors `.ax-list__trailing` and descendants
  to `on-secondary-container`; Phase 5 QA must verify this override still wins.

---

## §3 — Fallback Triggers

Switch to Option B and do not patch baseline if:

- required diff touches selectors outside §26 List,
- selected-state override fails to recolor trailing icons,
- disabled-state override fails to recolor trailing icons,
- segmented container color creates contrast/layout regression,
- validator fails after patch,
- Playwright reports overflow or row height regressions,
- any Wave 1 smoke check outside List changes unexpectedly.

Option B action:

```txt
Add BACKLOG #33 — List baseline color alignment
Leave components.css unchanged.
Close v3.5.11 with honest mismatch note.
```

---

## §4 — Edit Protocol

Use:

- `apply_patch`,
- immediate readback,
- abort-on-mismatch.

Do not use fresh Write fallback.

Sequence:

1. Run validator pre-patch.
2. Snapshot baseline mtimes.
3. Apply CSS patch.
4. Read back the exact edited §26 region.
5. Run validator post-patch.
6. Run Playwright List QA.
7. Run Wave 1 smoke checks.
8. Proceed to mechanical close only if all pass.

---

## §5 — Playwright QA

List checks:

- segmented background computed color is not transparent,
- trailing icon computed color = `on-surface`,
- selected trailing icon computed color = `on-secondary-container`,
- disabled trailing icon computed color = disabled 38% mix,
- row heights remain `56 / 72 / 88`,
- segmented gap remains `2px`,
- ripple hosts remain item-only,
- static/container ripple remains `0`,
- mobile/tablet/desktop overflow remains `0`.

Viewport matrix:

- 390px,
- 768px,
- 1280px.

---

## §6 — Wave 1 Smoke

After baseline patch, verify:

- Button active morph from v3.5.9 still works.
- Icon button unaffected.
- FAB unaffected.
- Text field unaffected.
- Search bar unaffected.
- Button group unaffected.
- List lab page passes Phase 3 checks.

This is a lightweight smoke check, not a full re-QA of every closed component.

---

## §7 — Mechanical Close Scope

If patch succeeds, Phase 5 closes:

- `LIST-SPEC-AUDIT.md`
  - Phase 5 verdict ALL PASS.
  - Token-level mismatch resolved in-cycle.
- `LIST-MEASUREMENT-AUDIT.md`
  - Token comparison table updated from mismatch candidate to resolved.
  - Phase 3 QA notes recorded.
- `LIST-WP-MAPPING.md`
  - Phase 5 verdict.
- `MODULE-STATUS-MATRIX.md`
  - row #33 TODO -> DONE.
  - ripple sub-table CANDIDATE -> TARGET for interactive List rows.
  - distribution update: 11 DONE / 3 PARTIAL / 17 TODO / 3 RECORD ->
    12 DONE / 3 PARTIAL / 16 TODO / 3 RECORD.
- `CHANGELOG.md`
  - v3.5.11 entry.
- `ROADMAP.md`
  - v3.5.11 DONE, v3.5.12 Carousel NEXT.
- `CURRENT-STATE.md`
  - v3.5.11 closed, Wave 1 8/9.
- `NEXT-SESSION.md`
  - v3.5.12 Carousel handoff.

Do not create BACKLOG #33 if Option A succeeds.

---

## §8 — Non-Goals

Phase 5 does not:

- alter List structure beyond the two color fixes,
- change tokens.css,
- change theme.json,
- change style-guide.html,
- edit lab pattern content except if QA exposes a direct defect,
- create List JS,
- close Avatar #32,
- implement drag/reorder,
- implement listbox runtime,
- open BACKLOG #33 unless fallback triggers.

---

## §9 — Approval Gate

This plan is ready for review.

On approval, Phase 5 execution should run in strict mode because it includes a
baseline CSS patch.

