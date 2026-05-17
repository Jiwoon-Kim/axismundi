# Axismundi v3.5.11 — List #33 Phase 2 Plan

> **Status**: Plan-only. Awaiting approval before implementation.  
> **Component**: List #33  
> **Category**: Component Full-Spec  
> **Inputs**: Phase 0 report + Phase 1 audit trio  
> **Date**: 2026-05-17

---

## §0 — Plan Scope And Gate

Phase 2 will create the List lab artifacts that prove the Phase 1 audit
contract without touching baseline files.

Primary goals:

1. Create lab-scoped List catalog styling.
2. Create a pattern HTML page that covers standard/segmented, 1/2/3-line,
   slots, selected, disabled, static, action, and navigation rows.
3. Preserve the Phase 1 semantics lock: no canonical `button role=listitem`.
4. Apply Ripple v2 only to interactive row items, never the list container.
5. Keep Avatar as a composed leading-slot dependency, not folded into List.
6. Define Playwright pre-checks before Phase 3 user QA.

Phase 2 starts only after this plan is approved.

---

## §1 — Deliverables

### §1.1 — Deliverable Artifacts

Exactly two new implementation artifacts:

| File | Path | Purpose |
|---|---|---|
| `lab-list.css` | `products/reference-implementations/axismundi-lab/modules/list/lab-list.css` | Lab-scoped catalog layout, demo-only helpers, state captions, responsive guards |
| `lab-list-pattern.html` | `products/reference-implementations/axismundi-lab/modules/list/lab-list-pattern.html` | Formal List pattern catalog and QA surface |

Do **not** create:

- `lab-list.js`
- `LIST-RUNTIME-AUDIT.md`
- WordPress block registration files
- baseline CSS edits

### §1.2 — Phase Bookkeeping

At Phase 2 close, update only:

- `products/reference-implementations/axismundi-lab/modules/list/docs/LIST-SPEC-AUDIT.md`
  - criterion #3 Pattern HTML completeness -> PASS at Phase 2 level

Do not update:

- `CURRENT-STATE.md`
- `NEXT-SESSION.md`
- `CHANGELOG.md`
- `ROADMAP.md`
- `BACKLOG.md`
- `MODULE-STATUS-MATRIX.md`

Those are Phase 5 or session-boundary surfaces.

---

## §2 — Selector Policy

### §2.1 — Scope Marker

All demo-only CSS must be scoped under:

```css
.lab-list-demo { ... }
```

Allowed:

```css
.lab-list-demo .ax-list { ... }
.lab-list-demo .ax-list__item { ... }
.lab-list-demo .lab-list-caption { ... }
```

Forbidden:

```css
.ax-list { ... }                 /* UNSCOPED override */
.ax-list__item { ... }           /* UNSCOPED override */
[data-ax-ripple] { ... }         /* Ripple v2 owns this */
```

### §2.2 — Baseline Class Precision

Phase 2 implementation must use only baseline List classes:

- `.ax-list`
- `.ax-list--segmented`
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
- `.is-selected`

Do not invent:

- `.ax-list__body`
- `.list-item`
- `.md-list`
- `.ax-list-item`

---

## §3 — Semantics Lock

Phase 2 must implement the Phase 1 semantic decision tree.

### §3.1 — Static Rows

Use static list semantics:

```html
<ul class="ax-list">
  <li class="ax-list__item">
    ...
  </li>
</ul>
```

Static rows:

- do not get `data-ax-ripple`,
- do not get `role="button"`,
- do not get fake click handlers,
- may show selected-looking visual state only with caption explaining it is a
  static catalog specimen.

### §3.2 — Action Rows

Use native button:

```html
<button type="button" class="ax-list__item" data-ax-ripple="bounded">
  ...
</button>
```

Do not add `role="listitem"` to the button.

### §3.3 — Navigation Rows

Use native anchor:

```html
<a href="#" class="ax-list__item" data-ax-ripple="bounded">
  ...
</a>
```

Navigation specimens should use `href="#"` only as catalog scaffolding and
caption that production links require real URLs.

### §3.4 — Selectable Rows

Selected rows may be static catalog specimens:

```html
<button type="button"
        class="ax-list__item is-selected"
        aria-pressed="true"
        data-ax-ripple="bounded">
  ...
</button>
```

But Phase 2 must not imply a fully managed `listbox` runtime.

Required caption:

> Static catalog state — selected visual is fixed. Production selectable lists
> need an owner for focus, keyboard movement, and state persistence.

### §3.5 — Disabled Rows

Use Pattern A split:

- native disabled button row,
- aria-disabled plugin-managed anchor/custom row.

ARIA-disabled caption:

> `aria-disabled` communicates state only. Integrator code must suppress
> activation and update focus behavior.

---

## §4 — Ripple Protocol

List uses Ripple v2 only on interactive row items.

Allowed:

```html
<button class="ax-list__item" data-ax-ripple="bounded">...</button>
<a class="ax-list__item" data-ax-ripple="bounded">...</a>
```

Forbidden:

```html
<div class="ax-list" data-ax-ripple>...</div>
<ul class="ax-list" data-ax-ripple>...</ul>
<li class="ax-list__item" data-ax-ripple>...</li> <!-- when static -->
```

Phase 2 self-check:

- count `data-ax-ripple` hosts,
- verify every host is `.ax-list__item`,
- verify no `.ax-list` container has ripple,
- verify static rows have zero ripple hosts.

---

## §5 — Avatar / Icon / Media Slots

### §5.1 — Avatar

Avatar remains composed:

```html
<span class="ax-list__leading">
  <span class="ax-avatar"><span>AB</span></span>
</span>
```

Do not define Avatar CSS in `lab-list.css` except demo spacing wrappers if
absolutely necessary and scoped under `.lab-list-demo`.

### §5.2 — Icon System

Use Material Symbols pattern for List-owned icon specimens:

```html
<span class="ax-list__leading" aria-hidden="true">
  <span class="material-symbols-rounded notranslate ax-icon"
        translate="no"
        aria-hidden="true"
        draggable="false">inbox</span>
</span>
```

Icon-system dependency remains CURRENT conditional.

### §5.3 — Image

Use `.ax-list__leading-image` for image/media static specimen. Video slot is
deferred.

---

## §6 — Pattern HTML Structure

`lab-list-pattern.html` should use this section structure:

1. Header / status banner
2. Standard static list
3. Standard action list
4. Navigation list
5. Segmented list
6. One-line / two-line / three-line row heights
7. Leading slots: icon / Avatar / image
8. Trailing slots: text / icon
9. Selected state specimen
10. Disabled split: native disabled + aria-disabled plugin-managed
11. Ripple protocol specimen: interactive rows only
12. Long-content / narrow viewport stress specimen
13. WordPress mapping specimens
14. Code snippets
15. Cross-references
16. Playwright QA targets

Minimum specimen coverage:

| Specimen type | Minimum count |
|---|---:|
| Static list | 1 |
| Action button row list | 1 |
| Navigation anchor row list | 1 |
| Segmented list | 1 |
| 1-line row | 2 |
| 2-line row | 2 |
| 3-line row | 1 |
| Avatar leading row | 1 |
| Image leading row | 1 |
| Selected row | 1 |
| Native disabled row | 1 |
| aria-disabled plugin-managed row | 1 |
| Ripple interactive row | 2 |
| Static no-ripple row | 1 |

---

## §7 — CSS Plan

`lab-list.css` should include only:

- demo page layout,
- section rhythm,
- responsive demo grid,
- max-width wrappers,
- caption styling,
- code snippet styling,
- QA helper outlines if needed,
- narrow viewport containment,
- optional demo-only image placeholders.

It should not:

- redefine baseline `.ax-list` behavior unscoped,
- change token values,
- add public List tokens,
- alter Ripple v2 styles,
- alter Avatar styles,
- alter icon-system styles.

Expected size: 120-220 lines.

---

## §8 — WordPress Mapping Specimens

Pattern HTML may include WordPress-aware specimens:

- static editorial `core/list`-like specimen,
- settings row pattern,
- search result row,
- profile directory row with Avatar leading slot,
- notification row,
- navigation anchor row.

Captions must distinguish:

- static theme markup,
- native action/navigation,
- plugin-managed dynamic data,
- future behavior.

No WordPress block registration occurs in Phase 2.

---

## §9 — Playwright Pre-Check

Phase 2 execution should run Playwright before handoff to Phase 3.

Checks:

1. Single-line row height = 56px.
2. Two-line row height = 72px.
3. Three-line row height = 88px.
4. Segmented gap = 2px.
5. Leading icon box = 24px and vertically centered.
6. Leading image box = 56px.
7. Avatar row aligns content center.
8. Trailing text does not overlap label.
9. Selected row uses corner-large and selected colors.
10. Disabled row does not show hover/active state.
11. Focus-visible outline remains visible.
12. Interactive rows have `data-ax-ripple="bounded"`.
13. Static rows have no ripple host.
14. List container has no ripple host.
15. Mobile 390px viewport has no horizontal overflow.

Viewport matrix:

- 390px mobile,
- 768px tablet,
- 1280px desktop.

---

## §10 — Phase Bookkeeping

At Phase 2 close:

Update `LIST-SPEC-AUDIT.md`:

```txt
#3 Pattern HTML completeness    PASS at Phase 2
```

Optionally add a short Phase 2 evidence note:

- `lab-list.css` created,
- `lab-list-pattern.html` created,
- no `lab-list.js`,
- static/action/navigation semantics preserved,
- ripple hosts item-only,
- Avatar composition preserved,
- Playwright pre-check summary.

Do not update:

- `CURRENT-STATE.md`,
- `NEXT-SESSION.md`,
- release docs,
- matrix,
- backlog.

---

## §11 — File Scope

Allowed to create:

```txt
products/reference-implementations/axismundi-lab/modules/list/lab-list.css
products/reference-implementations/axismundi-lab/modules/list/lab-list-pattern.html
```

Allowed to edit:

```txt
products/reference-implementations/axismundi-lab/modules/list/docs/LIST-SPEC-AUDIT.md
```

Forbidden:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/theme.json
docs/v3.5.0/MODULE-STATUS-MATRIX.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md
CURRENT-STATE.md
NEXT-SESSION.md
```

---

## §12 — Edit Protocol

Use:

- `apply_patch`,
- readback,
- abort-on-mismatch.

Do not use automatic fresh Write fallback. If readback differs from expected
content, stop and report before attempting recovery.

---

## §13 — Validation Plan

Before Phase 2 implementation:

1. Run validator.
2. Snapshot baseline mtimes.
3. Verify no `lab-list.css` / pattern HTML exists yet.

After implementation:

1. Run validator.
2. Verify baseline mtimes unchanged.
3. Verify exactly two implementation artifacts were created.
4. Verify `lab-list.js` does not exist.
5. Verify no `[NEXT SESSION:]` markers.
6. Run selector/ripple grep:

```powershell
rg -n "data-ax-ripple" products/reference-implementations/axismundi-lab/modules/list
rg -n "role=\"listitem\"|div role=\"button\"" products/reference-implementations/axismundi-lab/modules/list
rg -n "^\\.ax-list|^\\.ax-list__" products/reference-implementations/axismundi-lab/modules/list/lab-list.css
```

Expected:

- `data-ax-ripple` only on interactive `.ax-list__item` rows.
- no `role="listitem"` in canonical interactive specimens.
- no unscoped `.ax-list` / `.ax-list__item` rules in `lab-list.css`.

---

## §14 — Phase 3 Entry Criteria

Phase 3 visual QA can start only when:

1. Validator PASS.
2. Baseline mtimes unchanged.
3. `lab-list.css` and `lab-list-pattern.html` exist.
4. `lab-list.js` does not exist.
5. Playwright pre-check has passed or any findings are documented.
6. Static/action/navigation row semantics are visible in pattern HTML.
7. Ripple host protocol passes grep.
8. Mobile overflow check passes.

---

## §15 — Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Pattern HTML repeats `button role=listitem` | High | Plan forbids it; grep required |
| Ripple applied to static row/container | High | Grep + Playwright check |
| Avatar CSS accidentally forked | Medium | Composition-only rule |
| Long label/trailing text overflow | Medium | Narrow viewport stress specimen |
| Selected state implies live listbox | Medium | Static catalog caption |
| aria-disabled mistaken for real disabled | Medium | Plugin-managed caption |
| Nested interactive controls | Medium | Avoid multi-action live specimens |
| WordPress mapping overclaims `core/list` | Low | Captions + WP-MAPPING boundary |

---

## §16 — Non-Goals

Phase 2 does not:

- edit baseline CSS,
- edit `style-guide.html`,
- edit `theme.json`,
- create `lab-list.js`,
- create `LIST-RUNTIME-AUDIT.md`,
- close Avatar #32,
- implement listbox JS,
- implement drag/reorder,
- implement virtualized lists,
- implement expand/collapse,
- implement Menu/Tabs/Nav behavior,
- register WordPress block styles,
- update CURRENT-STATE,
- update NEXT-SESSION,
- update release docs.

---

## §17 — Self-Check

```txt
Deliverables exactly two                  yes
lab-list.js forbidden                     yes
3-doc trio preserved                      yes
button role=listitem forbidden            yes
ripple item-only protocol                 yes
Avatar composition-only                   yes
core/list partial mapping preserved       yes
Playwright pre-check defined              yes
CURRENT-STATE/NEXT-SESSION untouched      yes
baseline edit forbidden                   yes
```

---

## §18 — Approval Gate

This plan is ready for review.

After approval, Phase 2 execution may create `lab-list.css` and
`lab-list-pattern.html`, then update `LIST-SPEC-AUDIT.md` criterion #3 at
Phase 2 close.

