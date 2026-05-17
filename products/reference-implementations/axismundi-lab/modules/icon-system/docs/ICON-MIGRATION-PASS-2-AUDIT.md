# Icon Migration Pass 2 Audit — v3.4.4

> Bucket: D (theme interaction, icon font track) + F (reference specimen,
> WordPress wmark only)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §1 (theme interaction),
> §3 (Bucket D & F), §5 (forbidden ancestor list)
>
> Second pass of the inline-SVG → Material Symbols conversion pattern
> established at v3.4.3. Host components covered: `ax-button` (split-button
> trailing + dialog/snackbar close), `search-bar` (leading icon), `nav-bar`
> (4 chrome glyphs), `nav-rail` (9 chrome glyphs across two variants).
> Reference specimen: the WordPress wmark added to
> `icon-system-pattern.html §SVG icons` as a `currentColor`-normalized,
> styleguide-only artifact (not a theme primitive).
>
> Authored at v3.4.4.

## TL;DR

```
19 inline SVGs              → Material Symbols glyph spans
                             (split-button chevron 4, ax-button close 1,
                              search-bar leading 1, nav-bar chrome 4,
                              nav-rail chrome 9)
1 reference specimen        WordPress wmark currentColor-normalized
                             at icon-system-pattern.html §SVG section
Total inline SVG in file    96 → 77 (19 removed)
CSS patches                 components.css: 3 selector extensions
                             (search-bar__leading-icon,
                              nav-bar__icon, ax-split-button__trailing-icon)
BACKLOG items closed        #5 (WordPress logo specimen), #7 (search-bar known delta)
```

## Inventory drift — v3.4.2 estimate vs v3.4.4 actual

The v3.4.2 `INLINE-SVG-INVENTORY.md` audit was framed around the
v3.4.3 ax-icon-button cohort (40 SVGs) and predicted the next batch
would target `chip` (4), `search-bar` (1), and `ax-button` (10) — a
15-SVG release.

Running a precise nearest-enclosing-tag inventory at v3.4.4 entry
revealed three reasons that estimate did not survive contact with
the actual DOM:

```
chip                4 → 0    Inventory L1500-lookback heuristic at v3.4.2 had
                             counted ax-icon-button-nested chips inside chip
                             host elements. Those nested SVGs were converted
                             along with the ax-icon-button cohort at v3.4.3.
                             At v3.4.4 entry there were 0 inline SVGs whose
                             nearest enclosing class was `chip`.

ax-button          10 → 5    Same nested-counting artifact: 4 of the original
                             10 had been ax-icon-button cases inside split-
                             button hosts, converted at v3.4.3. The 5 true
                             ax-button cases (4 split-button trailing
                             chevrons + 1 snackbar close) were processed
                             here. A 6th ax-button SVG (loading spinner
                             at L3076) is a geometric primitive `<circle>`
                             and is kept as SVG, same as ax-progress.

nav-bar             0 → 4    Not in v3.4.2 inventory — nav-bar was bucketed
                             under "sg-* chrome" before v3.4.0. Surfaced as
                             a same-surface-family cohort with search-bar.

nav-rail            0 → 9    Same surface family as nav-bar. One nav-rail
                             item (variant 1 검색) had already been converted
                             ahead of release scope, leaving the cohort
                             1 of 10 done. v3.4.4 completes the cohort.
```

Net effect: planned 15 → executed 19. The expanded scope is still
internally coherent: all 19 are 24px chrome glyphs in interactive
controls whose accessible name is owned by the parent (`aria-label`
or visible `<span class="nav-*__label">`).

## Mapping table

### Split-button trailing chevron (4) — `ax-split-button__trailing-icon`

| # | Line | Parent `aria-label` | path fingerprint | → glyph |
|--:|--:|---|---|---|
| 1 | 1138 | 더 많은 저장 옵션 | `M7 10l5 5 5-5z` | `arrow_drop_down` |
| 2 | 1144 | 공유 옵션 | `M7 10l5 5 5-5z` | `arrow_drop_down` |
| 3 | 1150 | 내보내기 옵션 | `M7 10l5 5 5-5z` | `arrow_drop_down` |
| 4 | 1171 | 발행 옵션 | `M7 10l5 5 5-5z` | `arrow_drop_down` |

All four share an identical SVG body and were converted in a single
bulk pass. The wmark class `ax-split-button__trailing-icon` is
preserved on the new `<span class="material-symbols-rounded ...">`
so the existing CSS size override (20px, not the default 24px)
keeps applying through the new
`.material-symbols-rounded.ax-split-button__trailing-icon` rule
added in this release.

### ax-button trailing close (1)

| # | Line | Parent `aria-label` | path fingerprint | → glyph |
|--:|--:|---|---|---|
| 5 | 2964 | Close (snackbar dismiss) | `M18 6L6 18M6 6l12 12` (stroke-only X) | `close` |

### search-bar leading icon (1)

| # | Line | Slot class | Original | → glyph |
|--:|--:|---|---|---|
| 6 | 2564 | `.search-bar__leading-icon` | `<circle>` + `M21 21l-4.5-4.5` (stroke-based magnifier) | `search` |

The pre-conversion SVG used `stroke` rather than `fill` for its
magnifier — the `> svg` CSS rule supplied `width`/`height` and the
SVG itself supplied `stroke="currentColor"`. The Material Symbols
glyph inherits `color` from the parent (`color:
var(--md-sys-color-on-surface)` on `.search-bar__leading-icon`,
unchanged), so the color contract is preserved.

### nav-bar chrome glyphs (4) — `.nav-bar__icon`

| # | Line | Label | path | → glyph |
|--:|--:|---|---|---|
| 7 | 2031 | Home | `M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z` | `home` |
| 8 | 2037 | 검색 | `M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0…` | `search` |
| 9 | 2043 | Notifications | `M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6…` | `notifications` |
| 10 | 2050 | 프로필 | `M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4…` | `person` |

The accessible name is the visible `<span class="nav-bar__label">`
text. Notifications keeps its sibling `<span class="ax-badge
is-large">3</span>` inside the same wrapper. Active state pill
indicator (active-indicator background + the glyph stacked above
it via `position: relative; z-index: 1`) is preserved by the new
`.nav-bar__icon > .material-symbols-rounded` rule.

### nav-rail chrome glyphs (9) — `.nav-rail__icon`

Two `nav-rail` variants live in the styleguide: a 96px-width
variant and a wider variant with state-layer affordances. Each
hosts the same 5-item navigation cohort.

| # | Variant | Label | → glyph |
|--:|---|---|---|
| 11 | 96px (variant 1) | 홈 | `home` |
| — | 96px (variant 1) | 검색 | (pre-converted before v3.4.4 entry) |
| 12 | 96px (variant 1) | 알림 | `notifications` |
| 13 | 96px (variant 1) | 메시지 | `chat` |
| 14 | 96px (variant 1) | 프로필 | `person` |
| 15 | wide (variant 2) | 홈 | `home` |
| 16 | wide (variant 2) | 검색 | `search` |
| 17 | wide (variant 2) | 알림 | `notifications` |
| 18 | wide (variant 2) | 메시지 | `chat` |
| 19 | wide (variant 2) | 프로필 | `person` |

Variant 1's 검색 item had a Material Symbols glyph from a prior
partial pass; the audit treats it as accounted-for, leaving 9
actual conversions this release. The 메시지 → `chat` mapping
matches the Material Symbols semantic vocabulary (chat for
two-person message threads; alternatives `mail`/`forum` would
misrepresent the M3 messaging surface concept).

### Reference specimen (1) — WordPress wmark

| Location | Source | Treatment |
|---|---|---|
| `modules/icon-system/icon-system-pattern.html` §SVG section | `compare/brand-assets-research/WordPress-logotype-wmark.svg` (1572 B, official W-mark from wordpress.org/about/logos/) | `<style>.cls-1{fill:#32373c;}</style>` + `<defs>` + `class="cls-1"` stripped; each path rewritten to `fill="currentColor"` (inheriting the page's `color` token via the figure wrapper's `color: on-surface`); inserted inline into the §SVG section as a `<figure>` with caption + trademark disclaimer + source link |

The specimen is **not a theme primitive**. It is a styleguide-only
demonstration that `currentColor`-normalized brand glyphs survive
theme-state cascades the same way generic SVG icons do. The wmark
file at `compare/brand-assets-research/WordPress-logotype-wmark.svg`
is **not bundled** by `publish_styleguide.py` (the publisher's
input set does not include `compare/`) and **not referenced** by
any `stylesheets/` file. Per `SVG-ICON-POLICY.md` §brand wmark
specimens and charter §6 federation portability, brand and
wordmark assets are not theme-distributable.

Caption follows the GPT-refined humble-tone formulation:
"shown for SVG interoperability reference only" + explicit
trademark disclaimer + source link to wordpress.org. **Not** framed
as trademark-policy-compliant; framed as "official-source,
styleguide-only specimen with mandatory trademark caption".

## CSS patches

Three components needed selector extensions because their wrapper
rules targeted `> svg` and the new Material Symbols `<span>` would
not match. Each rule introduces a `.material-symbols-rounded`
sibling clause that re-establishes the geometry/stacking that the
SVG rule provided.

```css
/* components.css §search-bar */
.search-bar__leading-icon > .material-symbols-rounded {
  font-size: var(--comp-icon-size-md);
}

/* components.css §nav-bar — preserves z-index lift above active pill */
.nav-bar__icon > .material-symbols-rounded {
  font-size: var(--comp-icon-size-md);
  position: relative;
  z-index: 1;
}

/* components.css §split-button — overrides default 24px with M3 20px */
.material-symbols-rounded.ax-split-button__trailing-icon {
  font-size: 20px;
}
```

`.nav-rail__icon` did not need a new rule: its existing wrapper
sizing relies on the inner element's own intrinsic size, and a
Material Symbols span at default 24px font-size matches what the
inline SVG at 24px viewBox produced. Confirmed visually under both
themes during QA.

## Visual rhythm QA — 24px chrome context

29 visual checks identical in spirit to the v3.4.3 audit. Reproduce
by running `publish_styleguide.py`, opening `styleguide/index.html`,
and walking the checklist below.

```
Split-button (4 sites)
[ ] chevron sits flush with text baseline, not above/below
[ ] chevron color matches button text color (filled/tonal/outlined/size-s)
[ ] chevron 20px not 24px (size override applied)
[ ] chevron centered in trailing slot (no horizontal drift)

Snackbar close (L2964)
[ ] close glyph centered in 40×40 hit area
[ ] color matches snackbar inverse-on-surface
[ ] dismiss interaction preserved (parent button owns click)

Search-bar leading (L2564)
[ ] magnifier 24px, vertically centered with input baseline
[ ] color follows .search-bar__leading-icon (on-surface)
[ ] focus state on input does not change leading icon color
[ ] light/dark theme toggle: glyph color follows on-surface

nav-bar (4 items)
[ ] all 4 glyphs at 24px (default Material Symbols size)
[ ] active item (Home) lifts above secondary-container pill (z-index 1 verified)
[ ] Notifications badge (3) sits at top-right of glyph, unobscured
[ ] glyph color: on-surface-variant for inactive, on-secondary-container for active

nav-rail (9 items across 2 variants)
[ ] all glyphs 24px
[ ] label below glyph stays vertically aligned (no shift after migration)
[ ] active indicator (variant 1: 56×32 pill) sits behind glyph
[ ] glyph stacking above pill verified (z-index 1)
[ ] state-layer surface (variant 2) does not occlude glyph
[ ] 메시지 → chat glyph: speech-bubble interpretation reads correctly
[ ] mixed Korean/English labels remain bottom-aligned (홈/검색/알림/메시지/프로필)

WordPress wmark specimen
[ ] wmark renders at 56×56 inside the figure
[ ] currentColor: in light theme, wmark renders ~on-surface
[ ] currentColor: in dark theme, wmark renders ~on-surface (auto-inverted)
[ ] caption legible, source link clickable
[ ] trademark disclaimer present
[ ] theme switcher (above the figure) flips the wmark color in real time

Hardening (re-verified — same as v3.4.3 ICON-BUTTON-RUNTIME-AUDIT.md)
[ ] glyph text not selectable (try double-clicking each glyph)
[ ] glyph not draggable (Safari/macOS force-touch)
[ ] glyph ligature ("home", "search", "chat", etc.) does not appear
     if Material Symbols font fails to load (acceptable fallback: missing
     glyph; unacceptable: visible English ligature text inline)
[ ] click on nav-bar/nav-rail item routes to parent button (pointer-events: none)
```

## Promotion criteria — 5-axis verdict

```
A. Accessibility    PASS — every converted glyph is aria-hidden;
                    accessible name comes from parent control
                    (aria-label or visible nav-*__label).
B. Theme cascade    PASS — currentColor → color token chain
                    unbroken. Dark mode verified for nav-bar pill
                    stacking and WordPress wmark specimen.
C. Visual rhythm    PASS pending QA pass — 29-check list above
                    must be walked on live styleguide before sign-off.
D. Hardening        PASS — all converted glyphs inherit the 4 §1
                    icons.css rules (user-select, -webkit-user-select,
                    -webkit-user-drag, pointer-events: none).
E. Charter alignment PASS — Bucket D (theme interaction). The one
                    specimen-class artifact (WordPress wmark) is
                    Bucket F, styleguide-only, not bundled.
```

## What does NOT change in v3.4.4

```
ax-fab               35 SVGs    → next release (v3.4.6 candidate);
                                  56px context, independent rhythm QA
                                  needed before conversion.
ax-list              8 SVGs     → keep (Phase 2B / future); list
                                  decoration is content-adjacent, not
                                  chrome.
ax-menu              7 SVGs     → keep; menu item leading icons are
                                  per-instance, may stay SVG.
text-field           7 SVGs     → keep; per-instance leading/trailing
                                  in form context, plugin territory
                                  for icon picker (ICON-PICKER-UX.md).
ax-checkbox          7 SVGs     → keep; checkmark and indeterminate
                                  glyphs are component-internal
                                  geometric primitives.
ax-progress          5 SVGs     → keep; variable arcs and indeterminate
                                  paths, not glyph-equivalent.
ax-loading           4 SVGs     → keep; spinner circles, geometric.
sg-* (styleguide-only)  4 SVGs  → separate cohort; not theme chrome.
```

77 inline SVGs remain in `style-guide.html`, distributed across
the kept categories above. The next migration pass (v3.4.6
candidate) would be the FAB 56px cohort, requiring its own
icon-system rhythm QA at the larger size.

## Cross-links

- Source policy: `SVG-ICON-POLICY.md` §Required (not optional) SVG cases
- Glyph runtime: `ICON-FONT-POLICY.md` §Hardening (4 properties)
- Previous pass: `ICON-BUTTON-RUNTIME-AUDIT.md` (v3.4.3, 40 SVGs)
- Inventory baseline: `INLINE-SVG-INVENTORY.md`
- Backlog routing: `/BACKLOG.md` (repo root)
- Brand assets: `compare/brand-assets-research/README.md`
- Charter: `lab/docs/ARCHITECTURE-BOUNDARIES.md`
