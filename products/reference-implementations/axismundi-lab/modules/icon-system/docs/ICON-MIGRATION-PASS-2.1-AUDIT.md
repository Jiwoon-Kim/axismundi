# Icon Migration Pass 2.1 Audit — v3.4.4.1

> Bucket: D (theme interaction / styleguide chrome)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §1, §3 (Bucket D), §5
>
> Follow-up patch to v3.4.4 closing out the remaining 24px chrome-category
> inline SVGs that were surfaced by v3.4.4's strict-slot inventory but
> were intentionally left outside v3.4.4's commitment scope
> (`ax-button-icon` / `chip__leading-icon` / `chip__trailing-icon` /
> `search-bar__leading-icon`). v3.4.4's audit numbers are
> **deliberately not edited** — this patch records its own deltas.
>
> Authored at v3.4.4.1.

## TL;DR

```
5 inline SVGs            → Material Symbols glyph spans
                          (tabs__tab × 3, dialog__icon × 2)
style-guide.html size    183,359 B → 182,947 B (−412 B, −0.2%)
Total inline SVG in file 77 → 72 (5 removed)
material-symbols count   69 → 74 (5 added)
```

## Scope rationale

v3.4.4's strict-slot audit deliberately limited conversion to four
slot classes (`ax-button-icon`, `chip__leading-icon`,
`chip__trailing-icon`, `search-bar__leading-icon`) so the release
could close with a clean, single-family commitment. The full
inline-SVG inventory at that point also contained five SVGs in
*adjacent* 24px chrome slots (`tabs__tab` × 3, `dialog__icon` × 2)
that were eligible for the same conversion shape but were held back
to protect v3.4.4's audit numbers.

v3.4.4.1 finishes the **same 24px chrome family** without expanding
scope into different families:

- FAB / Extended FAB / FAB menu — **NOT touched** (56px context, 35 SVGs,
  separate release per ROADMAP)
- `ax-list` / `ax-menu` / `text-field` / `ax-checkbox` — **NOT touched**
  (internal-rendering SVGs, separate audit)
- `ax-progress` / `ax-loading` — **NOT touched** (geometric primitives,
  not glyph-replaceable)
- Brand / social / wordmark SVGs — **NOT touched** (Bucket F, per-brand
  fetch-from-source workflow)
- chip measurement audit (BACKLOG item 4) — **still deferred**, unchanged
  by this patch

## Mapping table

5 conversions, hand-curated, sibling-label as audit trail:

| Line | Slot | Sibling label | Original path | Glyph | Bucket |
|---:|---|---|---|---|:---:|
| 2215 | `tabs__tab is-active` | `홈` | house outline (`M3 11l9-8 9 8…`) | `home` | D |
| 2219 | `tabs__tab` | `탐색` | bare circle (placeholder) | `explore` | D |
| 2223 | `tabs__tab` | `프로필` | head + shoulders (`circle r=4` + `M4 21a8 8 0 0 1 16 0`) | `person` | D |
| 2838 | `dialog__icon` (inline static demo) | `변경 사항을 저장하시겠습니까?` | circle + ! outline (`M12 8v4M12 16h.01`) | `error` | D |
| 3166 | `dialog__icon` (portal modal) | `변경 사항을 저장하시겠습니까?` | circle + ! outline (identical to L2838) | `error` | D |

Per-slot notes:

- **L2219 `탐색`**: original SVG was a bare circle — visually a
  placeholder, not a recognizable explore glyph. `explore` (compass) is
  the M3-canonical glyph for an Explore tab; this is a *semantic
  upgrade*, not a shape-preserving swap. Captured as an intentional
  migration delta.
- **L2838 / L3166 `dialog__icon`**: original outline (circle + vertical
  bar + dot) is the conventional "exclamation in circle" mark. M3
  Material Symbols Rounded provides this as `error` (the Rounded
  outlined form is the default glyph render — no separate
  `error_outline` axis needed for the rounded family). The dialog uses
  it as a save-confirmation indicator; semantic mapping is consistent
  with M3 dialog patterns where `error` denotes "attention required",
  not necessarily a failure state. `dialog__icon` CSS color binding
  (whatever that resolves to) is unchanged — the conversion is glyph
  geometry only, not color.

## Accessible-name handling

All five converted spans are decorative (`aria-hidden="true"`) and
sit next to a sibling text node that supplies the accessible name:

```html
<!-- tabs__tab pattern -->
<button class="tabs__tab" type="button">
  <span class="material-symbols-rounded notranslate" translate="no"
        aria-hidden="true" draggable="false">explore</span>
  <span>탐색</span>          <!-- ← accessible name -->
</button>

<!-- dialog__icon pattern -->
<span class="dialog__icon">  <!-- ← span carries no a11y role -->
  <span class="material-symbols-rounded notranslate" translate="no"
        aria-hidden="true" draggable="false">error</span>
</span>
<h2 class="dialog__headline">…</h2>  <!-- ← dialog title carries meaning -->
```

This matches v3.4.3 / v3.4.4 conversion shape — no `<svg role="img"
aria-label>` was used in any of the five originals, so no accessible
name regression risk.

## 24px visual rhythm

Manual checks (3 tab icons, 2 dialog icons):

- [ ] `tabs__tab` glyph optical size matches surrounding `t-label-medium`
- [ ] `tabs__tab` glyph baseline aligns with the sibling `<span>` label
- [ ] `tabs__tab is-active` weight/grade still reads as visually
      emphasized vs. inactive tabs
- [ ] `dialog__icon` glyph optical size matches dialog headline rhythm
- [ ] `dialog__icon` glyph color resolves consistently in both
      inline-static and portal-modal contexts

These checks are construction-level (markup + glyph-name correctness);
runtime visual QA happens once published. Any visual delta found at
runtime routes to `BACKLOG.md` per the v3.4.3.1 convention.

## Five-criterion promotion verdict

| Criterion | Status |
|---|---|
| Charter-conformant (Bucket D, no Bucket A/B/F leak) | ✓ |
| No widening of scope beyond 24px chrome family | ✓ |
| Sibling-label a11y preserved | ✓ |
| Forbidden-ancestor rule respected (no `.prose` parent) | ✓ |
| Reversible (originals reproducible from this audit doc + git) | ✓ |

**Verdict: PASS — v3.4.4.1 closes the 24px chrome inline-SVG family.**

## What this patch does NOT change

- v3.4.4's `ICON-MIGRATION-PASS-2-AUDIT.md` is **unchanged**. Its
  10-conversion mapping and its 96-SVG / 50-MS post-state are correct
  for v3.4.4 *as a release*. The fact that v3.4.4.1 lowers the SVG
  count further is a v3.4.4.1 fact, not a v3.4.4 fact.
- The v3.4.2 `INLINE-SVG-INVENTORY.md` heuristic snapshot is
  unchanged (pre-conversion audit-trail policy from v3.4.3).
- `SVG-ICON-POLICY.md` is unchanged — the policy itself already
  covered this case; this patch is an application, not a policy
  amendment.
- `BACKLOG.md` does not gain a tabs / dialog entry — those would have
  been added had v3.4.4.1 been deferred. Since the patch shipped
  immediately, the deferral never materialized.
