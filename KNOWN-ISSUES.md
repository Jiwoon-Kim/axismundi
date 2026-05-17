# Known Issues

> Active issues identified post-v3.3.0 freeze that affect downstream work. Each issue has a target version for resolution.

---

## #1 — Lab inherits pre-lab-era contamination from prototype styleguide

**Discovered**: 2026-05-13 (post-v3.3.0 freeze)
**Affected**: `products/reference-implementations/axismundi-lab/style-guide*.html` + propagated to `/styleguide/` publish surface
**Target resolution**: v3.3.1
**Severity**: blocks pilot probe (v3.3.2)
**Resolved**: v3.3.1

### Description

When v3.3.0 promoted lab to active visual authority and deprecated the prototype, the lab styleguide files inherited contamination from the prototype styleguide that pre-dates the lab/prototype separation (introduced in v3.2.2). Specifically:

- Some sections in `style-guide.html`, `style-guide-blocks.html`, and/or `style-guide-prose.html` contain code that was authored before lab governance rules were established
- The contamination propagates to the publish surface (`/styleguide/`) because publish is a lab mirror (Constitution Article 12)
- The exact affected sections will be identified by the user when applying the rollback

### Resolution plan

1. **Rollback** affected styleguide sections to local clean backup (held by user outside this monorepo)
2. **Re-apply** any v3.2.x+ improvements that should survive the rollback (font/icon integration, GRAD sync, etc.)
3. **Apply targeted fixes** to lab to make it fully clean
4. **Re-run** `tools/generators/publish_styleguide.py` to propagate clean lab to `/styleguide/`
5. **Validate** with `tools/validators/validate_theme_pilot.py` — should remain 1.000 PASS
6. **Tag as v3.3.1** with CHANGELOG entry

### Why this blocks pilot probe

The pilot block theme (originally planned for v3.3.1, now v3.3.2) is built FROM the lab styleguide. Constructing the pilot before lab cleanup would either:
- Carry the contamination into the pilot, or
- Require re-construction once lab is fixed

Substrate-first principle (chunk-authoring Rule 4 in `products/_archive/_pre-monorepo-reports/cowork-kb-operating-rules/`): substrate must be clean before things are built on top of it.

### Anti-patterns to avoid

- ❌ Skipping lab cleanup and proceeding directly to pilot
- ❌ Cleaning *only* the publish surface (`/styleguide/`) — that's a derivative; lab is the source
- ❌ Discarding the local backup before verifying it covers all needed clean content
- ❌ Re-introducing the prototype as a reference for "what the styleguide should look like" — prototype is archived for a reason

### Resolution notes (v3.3.1)

**Sections actually affected** (identified during cleanup):

1. **`style-guide.html`** — text-field demo subsections (lab L2400–2540):
   - Lab had added "Filled — 7 configurations" + replaced backup's three Korean-first subsections ("Prefix · Suffix · Counter · Native validation" / "Leading · Trailing icons" / "Textarea") with a generic English "Outlined — 7 configurations" M3 reference matrix.
   - Korean labels (가격₩/KRW, 제목, 검색, 이메일, 메시지 작성), native HTML5 validation demo (pattern + `:user-invalid`), and character counter demo were lost.
   - **Resolution**: surgical rollback to backup. Lab's hunks 1–4 (logical CSS property `inset-inline-start`, ARIA `role="radio"` + `aria-checked` on theme switcher) and hunks 8–9 (slider `style="--_value: N%"` initial fill) preserved as legitimate i18n / a11y / UX improvements.

2. **`components.css`** — Time picker section (lab L5616–5882, Chunk H4):
   - Lab held an earlier, less-mature Time picker that regressed all Phase 1B work documented in v0.4.0 / v1.0.0-rc1:
     - `<input type="text" inputmode="numeric">` → `<button>` (lost typeable semantic markup)
     - `<fieldset>` + `<input type="radio">` + `<label>` AM/PM selector → `<div>` + `<button>` (lost semantic radio group + native keyboard arrow navigation)
     - `.is-24h` modifier documentation removed
     - Logical properties (`inline-size`, `block-size`) → physical (`width`, `height`) (lost RTL support)
     - CSS custom property tokens (`gap: var(--space-md)`, `padding: var(--space-lg)`) → raw pixel literals (`gap: 8px`, `padding: 24px`) (violates token-based design-system policy)
     - State-layer hover tint via `color-mix()` with state-layer-opacity tokens removed
     - `line-height: 0` alignment workaround for inline line-box (with full rationale comment) removed
   - **Resolution**: wholesale replacement of the Time picker section with the backup's Phase 1B version. Anchored on the `Chunk H4 — Time picker` and `Date / Time picker — usage patterns` section headers (both files share these anchors).

3. **`components.css` L1129** — `.text-field__input:has(~ .text-field__suffix) { text-align: end; }` rule:
   - Removed by lab. The Korean 가격/KRW form (and any future suffix-bearing input) depends on this for visual flush-with-suffix alignment.
   - **Resolution**: rule + rationale comment restored from backup.

4. **`prose.css`** — `§12 Icon font scope policy (Material Symbols)`:
   - v3.2.1 added this section to the **prototype** (per CHANGELOG v3.2.1: "stylesheets/prose.css §12 — icon scope enforcement"). When lab was constructed, prototype's prose.css §12 was NOT carried over. Backup also lacks §12 (pre-dates v3.2.1). The publish surface therefore shipped without the federation-portability scope enforcement that v3.2.1 had introduced.
   - **Resolution**: §12 block (the `.prose [class*="material-symbols"] { font-family: inherit !important; ... }` enforcement) copied from `products/_archive/axismundi-prototype/stylesheets/prose.css`. Sections TOC at top of lab `prose.css` updated to include §12.

**Sections deliberately NOT rolled back** (legitimate v3.2.x+ / Phase 2A work in lab):

- `base.css` §6.7 Skip link (WCAG 2.4.1, "메인 콘텐츠로 건너뛰기") + §6.8 `.visually-hidden` — new a11y utilities, KEPT
- `base.css` `pre code` / native `<select>` `background-color: var(--md-sys-color-surface)` + select 1px border — Phase 2A native select work, KEPT
- `prose.css` §2.1 / §2.2 paragraph + block-punctuation rhythm refactor — well-documented refactor that also fixes the `.prose ul/ol { margin-block: 0 }` cascade bug, KEPT
- `style-guide-prose.html` inline TOC scroll-spy `<script>` removal — the script was successfully migrated to `scripts/theme.js §5` (confirmed by inspecting both files); migration was the Phase 2B plan. KEPT.
- `blocks.css` table `is-style-vertical-borders` selector restructure (from `th + th, td + td` inter-cell separators → `th, td { border-inline-end }` + `:last-child { border-inline-end: 0 }` full-grid pattern) — matches M3 spec reference imagery per the new comment. KEPT.
- `components.css` 15 hunks of logical-CSS-property migration (`top/left/right` → `inset-block-start / inset-inline-*`, `border-top-{left,right}-radius` → `border-start-{start,end}-radius`, gradient `to right` → `to inline-end`, animation keyframes) — pure i18n / RTL improvement. KEPT.
- `components.css` focus-indicator change `3px` → `2px` (L1279/1293/1323) — per M3 spec. KEPT.
- `components.css` text-field error / disabled rules moved out and re-added with `-webkit-text-fill-color` for iOS Safari rendering parity. KEPT (net improvement).
- `components.css` slider color-token documentation block (L3881) — pure doc addition citing "M3 Slider spec — Ji-woon spec read 2026-05-08". KEPT.

**Sections noted as net loss but not rolled back** (deemed deliberate prune):

- `blocks.css` `.wp-block-table > figcaption` block-scoped styling — figcaption inherits base.css figcaption baseline instead.
- `blocks.css` `.wp-block-table.is-style-wrap` wide-table wrap variant — entire block style removed; if needed, can be re-added as v3.4 work.
- `prose.css` §11.6 (figcaption-inside-table-figure with `:has()`) and §11.7 (in-prose vertical-borders variant) — neither appears in the archived prototype either; these were short-lived backup-only rules superseded by the blocks.css scope split.

If any of the "deemed deliberate prune" items turn out to have been accidentally lost, they should be re-introduced in v3.3.2+ from this resolution note.

**Outcome**: substrate is now clean (verified 2026-05-13 via `tools/validators/validate_theme_pilot.py` returning 1.000 PASS across all four axes A/B/C/D). Pilot probe (v3.3.2) is unblocked.

---

## How to add a new issue

When discovering a new issue post-freeze:

1. Add a new entry in this file with `#N` numbering
2. Set target resolution version (typically the next minor version)
3. Update `ROADMAP.md` to reflect the new version's scope
4. If the issue blocks other planned work, mark it clearly
5. On resolution, leave the entry but add a `**Resolved**: vX.Y.Z` line

This file is the cross-version issue tracker. The CHANGELOG records what was done; this file records what is still pending.
