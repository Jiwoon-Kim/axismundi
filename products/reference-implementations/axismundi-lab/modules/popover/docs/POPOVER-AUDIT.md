# Popover / Menu Module Audit — v3.4.5


> **v3.4.8 Deletion Notice (added retrospectively):**
> The benchmark source files referenced throughout this document
> (`scripts/benchmark-interactions.js`, `stylesheets/benchmark-interactions.css`,
> and `style-guide-benchmark.html`) were **removed from the active tree at
> v3.4.8 Benchmark Surface Deletion**. Line ranges and quoted file paths in
> this document are HISTORICAL references — they describe where the source
> code lived during extraction, not where it lives now. Provenance is
> preserved in this audit document, CHANGELOG.md / ROADMAP.md, the
> v3.X.Y zip freezes, and git history.

> Bucket: D (theme interaction — runtime layer)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §1 (four layers), §4 (theme can / plugin should), §5 (forbidden ancestor list)
> Intake: see `lab/docs/BEER-CSS-INTAKE.md` (9 rules)
>
> Lab module extraction of the anchored menu / popover interaction
> already present in `benchmark-interactions.{css,js}` as Beer-CSS-derived
> demos. Reimplemented Axismundi-native into `lab/modules/popover/` per
> the established pattern (carousel v3.3.2, ripple v3.3.3,
> search-expansion v3.3.4).
>
> Authored at v3.4.5 (Phase 1 — skeleton; Phase 2/3 sections to be
> completed during implementation).

## Critical framing — what this module IS and IS NOT

```
Popover is a lab module in v3.4.5, NOT a baseline interaction layer.

The baseline styleguide retains static menu specimens (.ax-menu.is-open
demos), while live open/close/dismiss/focus-restoration behavior is
verified ONLY inside the popover module pattern page.

Same posture as ripple v3.3.3: lab module only, baseline promotion
deferred as a separate future decision under Charter §1.
```

## TL;DR

```
Functions extracted    6 (makeMenu, positionMenu, openBenchmarkMenu,
                        closeBenchmarkMenu, enableAnchoredMenuDemos,
                        enableSplitButtonMenus)
Code reimplemented in  lab/modules/popover/lab-popover.{css,js}
                        ├── lab-popover.js          12,241 B (~290 lines)
                        ├── lab-popover.css          4,693 B
                        ├── lab-popover-pattern.html 12,707 B
                        └── docs/POPOVER-AUDIT.md   (this file)
Style-guide changes    NONE to .ax-menu primitives in components.css
                        (kept as baseline visual specimen)
benchmark mark-up      /* EXTRACTED: v3.4.5 → modules/popover/… */
                        block comment above L96 makeMenu (50 lines)
Phase 4b cohort fix    data-theme-button → data-theme-set across 4
                        existing module pattern HTMLs (carousel,
                        ripple, search-expansion, icon-system) — 3
                        occurrences each, 12 total replacements.
                        New lab-popover-pattern.html authored with
                        data-theme-set from the start. Resolves
                        BACKLOG #9.
Publish surface        stylesheets count 11 → 12 (+lab-popover.css)
                        Total publish surface 18 → 19 files
Validator              1.000 / 1.000 / 1.000 / 1.000 PASS
                        (A schema / B theme / C css / D runtime)
```

## Bucket / Charter alignment

| Charter clause | Application |
|---|---|
| §1 — Four layers | Popover sits in the *lab module* layer for v3.4.5. Promotion to *theme interaction* is a separate future decision; v3.4.5 does NOT promote. |
| §3 — Bucket D | This module is Bucket D throughout: theme interaction runtime, no F-track (no brand/social/wordmark dependency), no plugin-only behavior. |
| §4 — Theme can / Plugin should | Theme provides the generic menu/popover runtime. Plugins are expected to feed *content* (menu items, command labels) into it via DOM. The runtime never reaches across into plugin space for command discovery. |
| §5 — Forbidden ancestors | `.prose`, `[contenteditable]`, and any element matching the forbidden-ancestor list bail out at trigger detection. See §"Forbidden-ancestor bail-out" below. |
| §6 — Federation portability | Popover runtime is *theme-side only*. Syndicated/federated content does not carry popover JS. Menus rendered by federation viewers (Mastodon, Misskey, etc.) get the static `.ax-menu.is-open` visual primitive at best — that is acceptable because menus require interaction to be useful and a federated viewer would not honor theme JS anyway. |

## Beer CSS intake summary

`benchmark-interactions.js` carries `'Beer CSS'` and `'beer-css'` markers
on the menu/popover section. Per `BEER-CSS-INTAKE.md`:

| Rule | Application to popover |
|---|---|
| 1. Opportunistic rough transplant — not authoritative source | Acknowledged. The benchmark code is reference, not source-of-truth. |
| 2. Reimplement Axismundi-native | Yes — `lab-popover.js` is hand-written, not copied. |
| 3. Scope to `.ax-button` / `.ax-icon-button` / `.ax-chip` only | Popover triggers are buttons; the contract holds. `.ax-menu` itself is *opened by* a button-class trigger, not contained inside one. |
| 4. No global click handler | All listeners are scoped: trigger-bound for open, menu-bound for keyboard nav, document-bound *only after open* for outside-click dismiss. |
| 5. No `.prose` leak | Trigger detection bails out if ancestor matches forbidden list (see §below). |
| 6. Token-based styling | `lab-popover.css` uses M3 surface / shape / elevation / motion tokens — no hard-coded color or radius. |
| 7. Reduced-motion respect | `prefers-reduced-motion: reduce` collapses the open/close transition to instant — see §"Reduced motion" below. |
| 8. Documented intake | This audit doc serves as the intake record. |
| 9. Promotion decision separate from intake | Lab-only at v3.4.5. Baseline promotion is a separate future charter decision, not this release. |

## Inventory — benchmark-interactions.js menu/popover functions

| L# | Function | Body lines | Purpose |
|---:|---|---:|---|
| 96 | `makeMenu(items)` | 27 | Build menu DOM from items array. |
| 125 | `positionMenu(trigger, menu)` | 17 | Compute anchored position from trigger rect. |
| 147 | `closeBenchmarkMenu()` | 9 | Dismiss currently-open menu. |
| 158 | `openBenchmarkMenu(trigger, menu)` | 10 | Open + close any prior + position. |
| 170 | `enableAnchoredMenuDemos()` | 28 | Wire `#components-menu .ax-menu` demos. |
| 200 | `enableSplitButtonMenus()` | 47 | Wire `.ax-split-button` second-button → menu. |
| **Total** | | **138** | Compact module — extraction fits in one file. |

Event types present in the section (verified):

```
click         6 occurrences
pointerdown   1 occurrence  (likely outside-click dismiss)
keydown       3 occurrences (likely Escape + arrow nav)
focusout      1 occurrence  (likely focus-leave dismiss)
```

Forbidden-ancestor handling already partially present (`.prose` 1,
`contenteditable` 1, `closest` 3) — but needs verification against the
full Charter §5 forbidden-ancestor list, which is broader. Extraction
will normalize the bail-out check against a single helper.

## Extraction plan (Phase 2)

### File layout

```
lab/modules/popover/
├── lab-popover.css                 (runtime-specific layer ONLY)
├── lab-popover.js                  (6 functions reimplemented)
├── lab-popover-pattern.html        (anchored menu + split-button menu)
└── docs/
    └── POPOVER-AUDIT.md            (this file)
```

### CSS scope discipline

```
components.css        .ax-menu visual primitive       ← UNCHANGED
                       (surface-container-low, corner-large,
                        elevation-2, opacity transition)

lab-popover.css       runtime-specific layer ONLY:
                      - anchored positioning helpers
                      - is-positioned-above / is-positioned-below modifiers
                      - keyboard-focus ring on .ax-menu__item
                      - reduced-motion override
                      - lab-pattern-page-only demo layout helpers
```

The goal is **no duplication / no drift**: `components.css` keeps the
visual primitive; `lab-popover.css` adds only what running the menu
adds. If both files end up defining the same property, `lab-popover.css`
loses (theme cascade — the primitive wins).

### JS reimplementation principles

1. **Single source of truth for open state**: one module-scoped variable
   tracking the currently-open menu element. Avoids the "two menus
   open simultaneously" failure mode.
2. **Document-level listeners attach only while a menu is open**: the
   outside-click and Escape listeners are added on open, removed on
   close. Avoids the always-on global handler that BEER-CSS-INTAKE.md
   rule 4 forbids.
3. **Open-click ≠ outside-click**: the same pointerdown that opens the
   menu must not immediately dismiss it. Solved by attaching the
   outside-click listener on `requestAnimationFrame` after open, so the
   triggering event has already propagated by the time the listener
   exists. (See a11y risk register §item 1 below.)
4. **Focus restoration is explicit**: store the focused element at open
   time; restore on close. Do not rely on `tabindex` browser defaults.
5. **ARIA triad is the contract**, not a cosmetic: trigger gets
   `aria-haspopup="menu"` + `aria-expanded` (dynamic) + `aria-controls`
   pointing to a menu with `role="menu"` whose children have
   `role="menuitem"`. Failing this is a P0 bug.

### benchmark `/* EXTRACTED */` markers (Phase 2 tail)

```js
// scripts/benchmark-interactions.js
/* EXTRACTED: v3.4.5 → modules/popover/lab-popover.js
 *   makeMenu, positionMenu, openBenchmarkMenu,
 *   closeBenchmarkMenu, enableAnchoredMenuDemos, enableSplitButtonMenus
 * Originals retained for benchmark archival per Charter §EXTRACTED policy.
 */
```

Same comment marker pattern for any CSS rules that move into
`lab-popover.css`. Originals are NOT deleted (Charter benchmark
archival policy from v3.3.2).

## a11y risk register

| # | Risk | Mitigation |
|---:|---|---|
| 1 | Open click and outside-click dismiss fire on the same event → menu opens and immediately closes | Attach the outside-click listener inside `requestAnimationFrame` after open; never on the same event tick |
| 2 | Focus not restored to trigger on dismiss → user lands in document body | `previousFocus = document.activeElement` at open; `previousFocus.focus()` at close. Verify it survives close-via-outside-click, close-via-Escape, close-via-item-activation |
| 3 | `.prose` / `[contenteditable]` ancestor → menu trigger fires inside user content (federation / editor risk) | At trigger event start: `if (target.closest('.prose, [contenteditable=""], [contenteditable="true"]')) return;` Apply across all event paths (click, keydown, focusin) |
| 4 | ARIA triad missing or inconsistent → screen-reader user has no idea the trigger opens a menu | Trigger must always have `aria-haspopup="menu"` + `aria-expanded` (sync'd) + `aria-controls=<menu-id>`. Menu must always have `role="menu"` + items `role="menuitem"`. Audit checklist enforces this. |
| 5 | Escape dismiss model differs from search-expansion (which uses two-step) → user confusion | Popover uses **single-step Escape**: one Escape → close menu → restore focus. Documented difference from search-expansion two-step Escape. |

## Forbidden-ancestor bail-out policy

A trigger event is **ignored** (the menu does not open) if any of the
following ancestors are found via `Element.closest()`:

```
.prose
[contenteditable=""]
[contenteditable="true"]
```

The list intentionally matches BEER-CSS-INTAKE.md rule 5. If Charter §5
expands the forbidden-ancestor list in a future release, the bail-out
check updates with it — single-source via a shared helper if practical.

**Why this matters**:

- Federation: syndicated content rendered inside `.prose` must not
  surface theme-side interaction. Even if the markup were preserved
  through ActivityPub serialization (it would not be), the interaction
  must not fire.
- Editor: the WordPress block editor places content inside
  `[contenteditable]`. A theme-side menu trigger firing during block
  editing would conflict with the editor's own command surface.

## Reduced motion

```css
@media (prefers-reduced-motion: reduce) {
  .ax-menu,
  .ax-menu.is-open {
    transition: none;
  }
}
```

Lives in `lab-popover.css`. Does NOT modify `components.css`
`.ax-menu` transition (the primitive keeps its motion; the runtime
layer overrides at lab-module level).

## Phase 4b — module pattern theme switcher cohort fix

Same release. The four existing module pattern HTMLs
(`lab-carousel-pattern.html`, `lab-ripple-pattern.html`,
`lab-search-expansion-pattern.html`, `icon-system-pattern.html`)
currently use `data-theme-button="…"` on their theme-switch buttons.
`theme.js` L526 listens for `[data-theme-set]`. v3.4.5 renames the
attribute across all four files to match the canonical contract used
in `style-guide.html`. The new `lab-popover-pattern.html` uses
`data-theme-set` from authoring — no migration needed for it.

Resolves BACKLOG #9. Re-enables BACKLOG #10 verification path
(ripple now testable under proper palette toggling).

## Five-criterion promotion verdict

| # | Criterion | Status |
|---:|---|:---:|
| 1 | **JS-off fallback** — static `.ax-menu.is-open` specimens in `style-guide.html` remain visual-only; lab module interaction is progressive enhancement | ✓ PASS |
| 2 | **M3 / state-layer compatibility** — reuses existing `.ax-menu` visual primitive in `components.css`, Material Symbols chrome icons throughout (`arrow_drop_down`, `add`, `folder_open`, `save`, `delete`, `settings`, `block`, `light_mode`, `dark_mode`, `contrast`), no parallel visual primitives created | ✓ PASS |
| 3 | **Reduced motion** — `prefers-reduced-motion: reduce` collapses `.ax-menu` open/close transition to instant in `lab-popover.css §4`; positioning logic produces no implicit motion | ✓ PASS |
| 4 | **Keyboard / a11y** — Escape (single-step), ArrowUp/Down, Home/End, Tab (dismiss + focus restoration), focus restoration on all close paths (Escape / outside-pointerdown / menuitem activation / Tab), ARIA triad enforced and auto-filled at init time (`aria-haspopup`, `aria-expanded`, `aria-controls`, `role="menu"`, `role="menuitem"`) | ✓ PASS |
| 5 | **Prose / federation isolation** — `isInForbiddenAncestor()` bail-out checks `.prose`, `[contenteditable=""]`, `[contenteditable="true"]` on every trigger event path (click + keydown). Pattern HTML §3 includes an explicit forbidden-ancestor demo card showing the no-op behavior | ✓ PASS |

### Verdict

```
PASS as a lab module.

The popover/menu runtime satisfies the v3.4.5 lab-module criteria for
progressive enhancement, forbidden-ancestor isolation, focus
restoration, Escape / outside dismiss, and open-scoped listeners.

It is NOT promoted to the baseline styleguide or theme interaction
layer in v3.4.5. Promotion is a separate future decision under
Charter §1 and is intentionally deferred.
```

한글 요약:

```
v3.4.5 기준 popover는 lab module로 PASS.
다만 baseline styleguide나 theme interaction layer로 승격된 것은 아니다.
```

### Internal contract checks (for traceability — not part of the user-facing 5-criterion verdict)

These confirm the same conclusions from a different angle:

- **Charter §1 / Bucket D**: confirmed — runtime sits in lab module layer, never touches baseline.
- **Beer CSS intake (BEER-CSS-INTAKE.md, 9 rules)**: confirmed — see §"Beer CSS intake summary" above. Notable: rule 4 ("no global click handler") satisfied by open-scoped listener attach/detach, which was a violation in the benchmark original.
- **a11y risk register (§"a11y risk register" 5 risks)**: confirmed — all 5 mitigations implemented and verifiable via Pattern HTML demos.
- **Forbidden-ancestor bail-out (Charter §5)**: confirmed — single helper `isInForbiddenAncestor()`, applied on both click and keydown event paths.
- **Reversibility (Charter EXTRACTED policy)**: confirmed — benchmark originals retained verbatim at `scripts/benchmark-interactions.js` L96–L247 with 50-line block comment above documenting the extraction.

## What this module does NOT do

- Does not promote popover/menu to baseline theme interaction layer
  (deferred — separate Charter §1 decision).
- Does not extract tooltip (`createTooltip` / `positionTooltip` /
  `enableTooltips`) — separate `lab-tooltip` module, future release.
- Does not redesign `.ax-menu` visual primitive in `components.css`.
- Does not implement command palette, dynamic menu registry, or
  editor-side menu builder.
- Does not handle FAB menu (56px context, separate Phase under
  v3.4.6 FAB conversion).
- Does not promote ripple to baseline (separate decision; v3.4.5 is
  popover-scope only).
- Does not change the styleguide-vs-module UX layout — that is
  BACKLOG #11, v3.5.0 candidate.
