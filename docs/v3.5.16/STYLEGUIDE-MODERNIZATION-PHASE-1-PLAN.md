# Axismundi v3.5.16 — Styleguide Modernization Phase 1 Plan

Status: Phase 5 closed  
Date: 2026-05-18  
Cycle: v3.5.16 Styleguide modernization + lab/module framing alignment  
Close: Phase 1 implementation plan approved; closed by v3.5.16 Phase 5.
Inputs:
- `docs/v3.5.16/MODERNIZATION-AUDIT.md`
- `docs/v3.5.16/STALE-STATE-AUDIT.md`
- `docs/v3.5.16/STYLEGUIDE-MODERNIZATION-PHASE-0-PLAN.md`
- `docs/v3.5.16/STYLEGUIDE-MODERNIZATION-PHASE-0-REPORT.md`

## §0. Phase 1 Verdict

Phase 1 is an implementation plan, not the implementation.

This plan locks the deliverables, edit surfaces, ordering, and fallback triggers for v3.5.16. Phase 2 must execute from this plan without expanding scope unless a blocker is discovered and routed through an explicit amendment.

Validator baseline before Phase 1 plan:

```txt
binding legitimacy: 1.000
promotion coverage: 1.000
public surface:     1.000
pilot readiness:    1.000
```

## §1. Cycle Shape

v3.5.16 remains a modernization cycle, not a component Full-Spec cycle.

Adopted lanes:

| Lane | Name | Status | Purpose |
| --- | --- | --- | --- |
| M | Charter/module framing amendment | Adopt | Align wording with GitHub Pages reality |
| N1 | Styleguide module links | Adopt | Add per-section validation specimen / record audit links |
| N2 | Mobile-first public shell | Adopt | Improve `index.html` and `style-guide.html` responsive UX |
| O | Lab pattern validation banners | Adopt | Standardize browsable lab pattern pages |
| P | BACKLOG + legacy hygiene | Adopt | Close overlaps and mark legacy audit shape honestly |
| Q | Opportunistic side fixes | Conditional adopt | Only #1 and #28 if patch remains tiny |

Deferred lanes:

| Lane | Decision |
| --- | --- |
| N3 dialog UX | Defer. Nice interaction, but not needed for first modernization pass. |
| N5 full dogfooding | Defer to BACKLOG #37. Requires Wave 2 nav/app-bar/tabs closure. |
| index Korean translation / language toggle | Defer to BACKLOG #35. |
| v4.0 directory restructure | Defer to BACKLOG #36. |

## §2. Execution Order

Phase 2 must execute lanes in this order:

```txt
M -> P -> Q -> N1 -> N2 -> O -> publish mirror -> Playwright QA -> Phase 5
```

Rationale:

1. Frame the public/lab contract first.
2. Clean stale bookkeeping before adding new navigation.
3. Apply small side fixes before visual QA, if still small.
4. Add navigation links and responsive shell.
5. Add lab banners after link targets are known.
6. Regenerate `/styleguide/` after source changes.

## §3. Lane M — Charter And Module Framing

### §3.1 Deliverables

Files:

- `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md`
- `products/reference-implementations/axismundi-lab/modules/README.md`
- `tools/generators/publish_styleguide.py`

### §3.2 Charter Wording Lock

Amend `PUBLIC-SURFACE-CHARTER.md §3.3`.

Current wording frames `lab/modules/*` as a validation surface and says it is not public API. That remains conceptually true, but GitHub Pages now serves the whole repository from `main` branch root. Therefore the wording must distinguish:

- **canonical public demo**: `/styleguide/`
- **browsable validation specimen**: `products/reference-implementations/axismundi-lab/modules/*/lab-*-pattern.html`
- **not public API**: lab selectors, lab helper classes, lab-only runtime hooks

Required wording:

```md
`lab/modules/*` is a module workspace and validation specimen surface.
It may be browsed from repository-root GitHub Pages for audit and QA
traceability, but it is not the canonical public API. The canonical visual
demo remains `/styleguide/`; downstream consumers should depend on graduated
baseline/component contracts, not lab-only selectors.
```

Also add a v3.5.16 note that the `lab` directory name is legacy naming retained until v4.0 directory restructure, tracked by BACKLOG #36.

### §3.3 Modules README Wording Lock

Update `products/reference-implementations/axismundi-lab/modules/README.md`.

The existing table says Pattern HTML and module docs are "No — lab-internal". Replace with a more precise contract:

| Artifact | Publish mirror `/styleguide/` | Repository-root Pages | Canonical? |
| --- | --- | --- | --- |
| Module CSS | Flattened into `/styleguide/stylesheets/` | Source browsable | CSS support layer |
| Module JS | Not mirrored | Source browsable | Validation/runtime evidence only unless promoted |
| Pattern HTML | Not mirrored | Source browsable | Validation specimen, not canonical demo |
| Module docs | Not mirrored | Source browsable | Audit evidence |

Keep the flattening rationale for `lab-<name>.css` filenames.

### §3.4 Publisher Comment Lock

Update `tools/generators/publish_styleguide.py` comments/docstring.

Replace "pattern HTML/docs are lab-internal artifacts" with:

- not copied into `/styleguide/`,
- still browsable on repo-root GitHub Pages,
- intentionally linked as validation specimens after v3.5.16,
- not canonical public API.

No generator behavior change is required for this wording alone.

## §4. Lane P — BACKLOG And Legacy Hygiene

### §4.1 Deliverables

Files:

- `BACKLOG.md`
- `docs/v3.5.0/MODULE-STATUS-MATRIX.md`

### §4.2 BACKLOG #11 / #34 Overlap

Lock:

- BACKLOG #11 is partially resolved by the v3.5.0 framework docs.
- The remaining UX surface is superseded by BACKLOG #34 and v3.5.16.
- Do not delete #11; preserve historical context and add cross-reference.

Required status wording:

```md
Framework portion resolved by v3.5.0 Public Surface Reframe.
Remaining styleguide <-> lab UX work superseded by BACKLOG #34 and v3.5.16.
```

### §4.3 BACKLOG #10 Close-Check

Lock:

- BACKLOG #10 "Lab ripple runtime verification" is closed by v3.5.6 Ripple v2 if Phase 2 readback confirms the item text matches the v3.5.6 contract.
- Expected close marker: `RESOLVED at v3.5.6 — Ripple v2 contract`.

Fallback:

- If #10 includes a narrow residual not covered by v3.5.6, keep it open and add "partially absorbed" wording.

### §4.4 BACKLOG #17 Close-Check

Lock:

- BACKLOG #17 "Text Input Corpus Audit" is closed by v3.5.7 Text field and v3.5.8 Search bar if Phase 2 readback confirms its requested surface is the input corpus now covered by those cycles.
- Expected close marker: `RESOLVED by v3.5.7 Text field + v3.5.8 Search bar`.

Fallback:

- If #17 includes combobox/date/time work beyond Text field/Search bar, mark as partially resolved and cross-reference the relevant later rows.

### §4.5 BACKLOG #13 Theme JS Publish 404

Lock:

- `style-guide.html` still references `scripts/theme.js`.
- `publish_styleguide.py` currently copies only `style-guide.js`.
- GitHub Pages therefore risks a console 404 in `/styleguide/`.

Decision:

- **Option A adopted**: copy `theme.js` into `/styleguide/scripts/` during publish.

Rationale:

- It removes the 404 without weakening source authority.
- It supports the current source HTML contract.
- It is safer than deleting script references from multiple source HTML files during a navigation modernization cycle.

Phase 2 must patch `publish_styleguide.py` to copy `theme.js` if present and then regenerate `/styleguide/`.

### §4.6 Snackbar / Tooltip Legacy Marking

Update rows #28 and #29 in `MODULE-STATUS-MATRIX.md` notes:

- Snackbar: `DONE (v3.4.10 legacy audit shape; predates v3.5.0 3/4-doc framework).`
- Tooltip: `DONE (v3.4.6 legacy audit shape; predates v3.5.0 3/4-doc framework).`

Do not reopen their audit docs.

## §5. Lane Q — Conditional Side Fixes

### §5.1 Adopted Side Fixes

Only two side fixes are in scope:

| Backlog | Decision | File Scope |
| --- | --- | --- |
| #1 Inline code font-size inheritance | Adopt if one-line CSS patch remains valid | `style-guide.html` only |
| #28 Icon button SVG wording cleanup | Adopt | `style-guide.html` + BACKLOG close marker |

### §5.2 #1 CSS Patch

Current evidence:

- `.sg-helper` exists.
- Helper text contains inline `<code>`.
- No `.sg-helper code` inheritance rule is present.

Patch:

```css
.sg-helper code,
.t-body-small code {
  font-size: inherit;
}
```

Location:

- `style-guide.html` inline style block near `.sg-helper`.

Fallback:

- If this causes snippet or type specimen regression, narrow to `.sg-helper code` only.

### §5.3 #28 Public SVG Wording Patch

Current evidence:

`style-guide.html #components-icon-button` still has SVG-era public wording:

```html
&lt;svg ...&gt;&lt;/svg&gt;
Markup is identical — author updates SVG fill/d on toggle.
```

Patch to Material Symbols wording:

```html
&lt;span class="material-symbols-rounded notranslate" ...&gt;add&lt;/span&gt;
```

and helper text:

```txt
Markup is identical — author updates the Material Symbols glyph name
and selected state as needed.
```

After patch, BACKLOG #28 may be marked resolved at v3.5.16.

### §5.4 Deferred Side Fixes

Do not touch:

- #2 Avatar size tokens
- #3 Floating toolbar selected color

These are component/baseline issues, not modernization side fixes.

## §6. Lane N1 — Styleguide Module Links

### §6.1 Deliverables

File:

- `products/reference-implementations/axismundi-lab/style-guide.html`

Generated file:

- `styleguide/index.html` after publisher run

### §6.2 Markup Pattern

Use existing styleguide structure; do not invent a parallel header class.

Pattern:

```html
<header class="sg-section__head">
  <div class="t-label-medium sg-section__eyebrow">Components</div>
  <h2 class="t-headline-medium">Button</h2>
  <a class="sg-lab-link t-label-large"
     href="modules/button/lab-button-pattern.html">
    Validation specimen
  </a>
</header>
```

For record-only entries:

```html
<a class="sg-lab-link t-label-large"
   href="modules/_records/AVATAR-RECORD-AUDIT.md">
  Record audit
</a>
```

### §6.3 Link Target Inventory

Add direct lab links to these 15 sections:

| Section id | Link |
| --- | --- |
| `components-button` | `modules/button/lab-button-pattern.html` |
| `components-icon-button` | `modules/icon-button/lab-icon-button-pattern.html` |
| `components-fab` | `modules/fab/lab-fab-pattern.html` |
| `components-fab-extended` | `modules/fab/lab-fab-pattern.html` |
| `components-button-group` | `modules/button-group/lab-button-group-pattern.html` |
| `components-card` | `modules/card/lab-card-pattern.html` |
| `components-text-field` | `modules/text-field/lab-text-field-pattern.html` |
| `components-search-bar` | `modules/search-bar/lab-search-bar-pattern.html` |
| `components-list` | `modules/list/lab-list-pattern.html` |
| `components-carousel` | `modules/carousel/lab-carousel-pattern.html` |
| `components-chip` | `modules/chip/lab-chip-pattern.html` |
| `components-snackbar` | `modules/snackbar/lab-snackbar-pattern.html` |
| `components-tooltip` | `modules/tooltip/lab-tooltip-pattern.html` |
| `components-date-picker` | `modules/date-time/lab-date-time-pattern.html` |
| `components-time-picker` | `modules/date-time/lab-date-time-pattern.html` |

Add record audit links to:

| Section id | Link |
| --- | --- |
| `components-avatar` | `modules/_records/AVATAR-RECORD-AUDIT.md` |
| `components-divider` | `modules/_records/DIVIDER-RECORD-AUDIT.md` |
| `components-badge` | `modules/_records/BADGE-RECORD-AUDIT.md` |

Do not add direct links to TODO / Wave 2 / Wave 3 sections that do not have matching lab patterns.

Infrastructure modules remain discoverable through the module index and are not attached to component sections in this cycle.

### §6.4 Publish Link Rewrite Requirement

Source `style-guide.html` lives under:

```txt
products/reference-implementations/axismundi-lab/style-guide.html
```

Therefore `href="modules/button/lab-button-pattern.html"` works in source.

Generated `/styleguide/index.html` lives under:

```txt
styleguide/index.html
```

Therefore Phase 2 must update `publish_styleguide.py` to rewrite:

```txt
href="modules/
```

to:

```txt
href="../products/reference-implementations/axismundi-lab/modules/
```

for generated HTML only.

This keeps source links short and generated GitHub Pages links correct.

### §6.5 CSS Pattern

Add styleguide-local CSS only in `style-guide.html` inline style block:

```css
.sg-section__head {
  display: grid;
  gap: var(--space-xs);
}

.sg-lab-link {
  justify-self: start;
  display: inline-flex;
  align-items: center;
  min-block-size: 40px;
  padding-inline: var(--space-md);
  border-radius: var(--md-sys-shape-corner-full);
  color: var(--md-sys-color-primary);
  text-decoration: none;
}
```

Exact visual treatment may be adjusted in Phase 2 as long as:

- it remains mobile-safe,
- it does not require new JS,
- it does not use Wave 2 navigation components,
- text does not overflow at 390px.

## §7. Lane N2 — Mobile-First Public Shell

### §7.1 Deliverables

Files:

- `index.html`
- `products/reference-implementations/axismundi-lab/style-guide.html`

Generated file:

- `styleguide/index.html`

### §7.2 Root Index Scope

`index.html` should be updated as a mobile-first public landing page.

Required outcomes:

- 390px viewport has no horizontal overflow.
- Primary links are thumb-sized.
- Public routes are clear:
  - Styleguide
  - Lab overview
  - Module index
  - Templates note
  - README
  - README.ko
  - LICENSE-MATRIX / NOTICE
- Existing author identity remains:
  - `KIM JIWOON`
  - `designbusan.ai.kr`
  - `Busan, Korea`

No language toggle and no `index.ko.html` in this cycle.

### §7.3 Styleguide Responsive Scope

`style-guide.html` should remain a single-page catalog but get a mobile-first shell.

Required outcomes:

- `body` / root layout has no horizontal overflow at 390px.
- Guide navigation wraps or scrolls safely.
- `sg-main` padding compresses on mobile.
- Code snippets scroll inside themselves, not the page.
- New module links remain reachable in each section header.

Do not introduce:

- app bar,
- nav bar,
- nav rail,
- tabs,
- dialog UX,
- new runtime JS.

Those belong to BACKLOG #37 / N3/N5.

### §7.4 Breakpoint Matrix

Phase 3 must verify:

| Viewport | Expectation |
| --- | --- |
| 390px | No horizontal overflow; landing cards and styleguide links stack cleanly |
| 768px | Navigation remains usable; styleguide content width stable |
| 1280px | Desktop layout remains at least as usable as current |

## §8. Lane O — Lab Pattern Validation Banners

### §8.1 Deliverables

Files:

The 16 current `lab-*-pattern.html` files:

- `button/lab-button-pattern.html`
- `button-group/lab-button-group-pattern.html`
- `card/lab-card-pattern.html`
- `carousel/lab-carousel-pattern.html`
- `chip/lab-chip-pattern.html`
- `date-time/lab-date-time-pattern.html`
- `fab/lab-fab-pattern.html`
- `icon-button/lab-icon-button-pattern.html`
- `list/lab-list-pattern.html`
- `popover/lab-popover-pattern.html`
- `ripple/lab-ripple-pattern.html`
- `search-bar/lab-search-bar-pattern.html`
- `search-expansion/lab-search-expansion-pattern.html`
- `snackbar/lab-snackbar-pattern.html`
- `text-field/lab-text-field-pattern.html`
- `tooltip/lab-tooltip-pattern.html`

Note: Phase 0 report counted 17 lab pattern surfaces including infrastructure coverage, but current filesystem inventory has 16 `lab-*-pattern.html` files plus record-only audits. Phase 2 must use filesystem inventory as authoritative and record the count in the final close.

### §8.2 Banner Markup

Insert one banner near the top of each pattern page, after the main page header if a header exists, otherwise immediately after the scoped `<main>` starts.

Markup:

```html
<aside class="lab-specimen-banner" role="note">
  <strong>Validation specimen.</strong>
  This page verifies the module implementation and audit evidence. The
  canonical public demo remains the styleguide.
</aside>
```

### §8.3 Banner CSS

Prefer a small shared inline style block only if each page already has local style utilities. Otherwise use existing page classes and minimal markup.

No new shared CSS file.

Do not modify module component CSS (`lab-*.css`) just to style the banner.

## §9. Publish Generator Requirements

File:

- `tools/generators/publish_styleguide.py`

Required Phase 2 changes:

1. Update comments/docstring for browsable validation specimens.
2. Copy `theme.js` to `styleguide/scripts/theme.js` if present.
3. Rewrite generated styleguide lab links:

```txt
href="modules/
→ href="../products/reference-implementations/axismundi-lab/modules/
```

4. Keep `/styleguide/` as a mirror, not a source authority.

After changes:

```powershell
python .\tools\generators\publish_styleguide.py
```

must regenerate `/styleguide/` successfully.

## §10. Non-Goals

Do not:

- edit baseline component CSS (`components.css`) except no edits are expected at all,
- edit `tokens.css`,
- edit `blocks.css`,
- edit `theme.json`,
- introduce Wave 2 navigation components,
- add `index.ko.html`,
- create a dialog-based module picker,
- restructure directories,
- rename `axismundi-lab`,
- remove `lab-` file prefixes,
- change GitHub Pages source,
- change repository visibility,
- modify WordPress pilot theme files.

## §11. Validation Plan

### §11.1 Command Validation

Run:

```powershell
python .\tools\validators\validate_theme_pilot.py
npm test
python .\tools\generators\publish_styleguide.py
python .\tools\validators\validate_theme_pilot.py
```

Expected:

- validator stays `1.000 / 1.000 / 1.000 / 1.000 PASS`,
- npm test PASS,
- publish generator PASS,
- `styleguide/scripts/theme.js` exists after publish,
- `styleguide/index.html` contains rewritten lab links.

### §11.2 Link Checks

At minimum, check generated links for:

- `styleguide/index.html` -> `../products/reference-implementations/axismundi-lab/modules/button/lab-button-pattern.html`
- `styleguide/index.html` -> `../products/reference-implementations/axismundi-lab/modules/_records/AVATAR-RECORD-AUDIT.md`
- root `index.html` public routes.

### §11.3 Playwright / Browser QA

Use Playwright or browser inspection for:

- root `index.html` at 390 / 768 / 1280,
- `styleguide/index.html` at 390 / 768 / 1280,
- one component module link from styleguide,
- one record audit link from styleguide,
- one lab pattern page banner.

Pass criteria:

- no horizontal overflow at 390px,
- module links are visible and clickable,
- generated lab links resolve,
- styleguide remains usable on desktop,
- lab banner does not obscure content.

## §12. Fallback Triggers

Switch to narrower scope if:

| Trigger | Fallback |
| --- | --- |
| Link rewrite breaks source styleguide local browsing | Keep source links and only rewrite generated publish HTML |
| `theme.js` conflicts with `style-guide.js` | Copy still allowed, but document duplicate-handler behavior; if visible regression occurs, remove source script references instead |
| Mobile CSS causes section layout regression | Keep root index mobile update, defer styleguide responsive shell to v3.5.17 |
| Lab banner insertion creates inconsistent page layout | Use text-only banner with existing classes, no new CSS |
| BACKLOG close-check evidence is ambiguous | Mark partially resolved instead of closed |

## §13. Phase 2 Entry Criteria

Phase 2 may begin when this plan is approved and:

- no additional lanes are added,
- N3/N5 remain deferred,
- Lane Q remains limited to #1 and #28,
- generator link rewrite is accepted,
- `theme.js` copy decision is accepted.

## §14. Phase 5 Preview

Phase 5 close should update:

- `CHANGELOG.md` v3.5.16 entry,
- `ROADMAP.md` v3.5.16 DONE + next route,
- `CURRENT-STATE.md`,
- `NEXT-SESSION.md`,
- BACKLOG #34 status if modernization closes it,
- BACKLOG #1 / #10 / #13 / #17 / #28 as applicable,
- v3.5.16 docs status.

Matrix status counts should not change unless Phase 2 discovers a status-table issue. This is a public surface modernization cycle, not a component promotion cycle.
