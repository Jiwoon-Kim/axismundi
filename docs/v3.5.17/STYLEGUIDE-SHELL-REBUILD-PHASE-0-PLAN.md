# Axismundi v3.5.17 — Styleguide Shell Rebuild Phase 0 Plan

Status: Phase 0 plan v1.0  
Date: 2026-05-18  
Cycle: v3.5.17 styleguide shell rebuild + mobile reading polish  
Scope: plan-first for actual styleguide UX modernization after v3.5.16 framing close

## §0. User Request Log — Do Not Abstract Away

These are direct acceptance requirements, not loose inspiration.

```txt
1. Mobile styleguide must not show the desktop aside as the primary shell.
2. Mobile styleguide must use a top app bar.
3. Mobile navigation must open from a menu icon button.
4. Mobile navigation must be a Sheet static side modal / drawer pattern.
5. The drawer must contain canonical section nav.
6. Theme switcher should remain conceptually in the nav/shell area.
7. Theme switcher must become an icon toggle pattern, not text-chip-only.
8. The icon theme switcher should be reusable across styleguide, index, and
   module/lab pages later.
9. Body section order must follow canonical nav order.
10. 38-ish existing sections and accumulated visual specimens are valuable;
    preserve them rather than rebuilding every demo from scratch.
11. Body sections also need mobile reading polish:
    - color palette swatches should size/wrap correctly;
    - visual demos should lead;
    - long explanatory copy should become read-more/disclosure;
    - the result should be easier to scan on 390px.
12. v3.5.16 closed too early by checking plan execution, not user vision.
    v3.5.17 must close only after these acceptance criteria are verified.
```

## §1. Acceptance Criteria

Phase 5 close is forbidden unless all checked items pass or are explicitly
deferred by user approval.

```txt
Shell / navigation:
  □ 390px viewport: desktop aside is not visible as the primary shell.
  □ 390px viewport: top app bar is visible.
  □ Top app bar includes a real icon button menu trigger.
  □ Menu trigger opens and closes a Sheet static side modal / drawer.
  □ Drawer contains canonical section nav.
  □ Drawer contains icon theme switcher.
  □ Desktop / tablet preserve an efficient sidebar/aside experience.
  □ Body section order equals canonical nav order.

Theme switcher:
  □ Theme switcher is icon-based (light / dark / auto icons).
  □ It remains backed by the existing data-theme-button contract or a
    backward-compatible successor.
  □ Existing theme persistence via localStorage still works.
  □ Pattern is reusable for index/module pages later.

Body mobile polish:
  □ Color palette grids fit 390px without oversized cards.
  □ Visual demo content appears before long explanatory text where practical.
  □ Long explanation copy uses native read-more disclosure.
  □ Disclosure uses accessible native <details><summary>.
  □ Existing semantic content is preserved.
  □ Existing 18 styleguide lab/record links are preserved.
  □ Existing 16 lab pattern validation banners are preserved.

Verification:
  □ Playwright 390 / 768 / 1280 overflow = 0.
  □ Drawer open/close works by click and Escape.
  □ Theme switcher works in closed and open navigation states.
  □ Validator remains 1.000 / 1.000 / 1.000 / 1.000 PASS.
  □ npm test PASS.
```

## §2. Non-Goals

Do not:

- rebuild all component specimens from scratch,
- rewrite the 37 canonical body sections wholesale,
- change component baseline CSS (`components.css`),
- change tokens (`tokens.css`),
- introduce Wave 2 App bar/Nav bar/Nav rail/Tabs as if complete,
- implement N3 global module-list dialog,
- implement `index.ko.html` or language toggle,
- restructure directories or rename `axismundi-lab`,
- retire `lab-*` prefixes,
- change GitHub Pages source.

## §3. Recommended Strategy

Adopt **navigation shell rebuild + section preservation**.

Rejected:

- Full styleguide rebuild from scratch: too much risk to accumulated Wave 1
  visual QA assets.
- Tiny patch on top of v3.5.16: too weak; it repeats the failure mode.

Adopted:

- preserve body sections and specimens,
- replace / rebuild the styleguide shell,
- add mobile-first reading polish around existing content,
- keep changes local to `style-guide.html`, styleguide scripts, and generated
  `/styleguide/` mirror.

## §4. Existing Runtime Inventory

`products/reference-implementations/axismundi-lab/scripts/theme.js` already has
an off-canvas nav drawer skeleton:

```txt
§1 Off-canvas nav drawer
  - [data-toggle-nav]
  - <dialog id="nav-drawer" class="ax-sheet ax-sheet--side-modal">
  - [data-close-modal]
  - showModal()
  - ESC / backdrop close
  - viewport guard at min-width: 1024px
```

This is close to the requested Sheet static side modal pattern. Phase 1 must
decide whether to reuse it directly in `style-guide.html` or keep styleguide
shell logic in `style-guide.js`.

Current `style-guide.html` has:

```txt
desktop shell:
  .sg-layout grid
  .sg-sidebar sticky 240px
  .sg-main content

mobile fallback:
  @media (max-width: 720px)
    .sg-layout -> 1fr
    .sg-sidebar -> static visible block above content
```

The mobile fallback is the exact behavior to replace.

## §5. Lane A — Navigation Shell Rebuild

### Goal

Mobile gets a top app bar + menu icon + Sheet drawer. Desktop/tablet keeps an
efficient aside.

### Phase 1 Must Decide

1. Markup structure:

```html
<header class="sg-top-app-bar">
  <button class="ax-icon-button ..." data-toggle-nav aria-controls="sg-nav-drawer">
    <span class="material-symbols-rounded">menu</span>
  </button>
  <span class="sg-top-app-bar__title">Axismundi</span>
  <div class="sg-top-app-bar__theme">...</div>
</header>

<dialog id="sg-nav-drawer" class="sg-nav-drawer ax-sheet ax-sheet--side-modal">
  <button data-close-modal>...</button>
  ...nav + theme switcher...
</dialog>
```

2. Whether desktop aside and mobile drawer share duplicated nav markup or one
   source is cloned by script.

Recommended: duplicate small nav markup in HTML for Phase 2 clarity, then
assert equality in Playwright. Avoid runtime cloning for this pass.

3. Breakpoints:

```txt
<= 720px:
  top app bar visible
  desktop aside hidden
  drawer available

> 720px:
  desktop aside visible
  top app bar hidden or minimized
```

4. Runtime:

Recommended: use existing `theme.js §1` data attributes. If styleguide needs
local differences, add small styleguide-only wrapper in `style-guide.js`, but
do not invent a second drawer protocol.

## §6. Lane B — Icon Theme Switcher

### Goal

Replace text-chip-only theme switching with an icon toggle pattern that still
uses the existing theme persistence contract.

### Contract

Keep:

```txt
data-theme-button="light|dark|auto"
aria-checked
aria-pressed
.is-selected
localStorage key: axismundi.theme
```

Preferred visual:

```html
<div class="sg-theme sg-theme--icons" role="radiogroup" aria-label="Theme">
  <button class="ax-icon-button ..." data-theme-button="light" role="radio">
    <span class="material-symbols-rounded">light_mode</span>
  </button>
  <button class="ax-icon-button ..." data-theme-button="dark" role="radio">
    <span class="material-symbols-rounded">dark_mode</span>
  </button>
  <button class="ax-icon-button ..." data-theme-button="auto" role="radio">
    <span class="material-symbols-rounded">contrast</span>
  </button>
</div>
```

Phase 1 must verify glyph choices. Candidate:

- light: `light_mode`
- dark: `dark_mode`
- auto: `contrast` or `routine`

Do not remove the theme behavior from `style-guide.js` without checking module
patterns and `theme.js` expectations.

## §7. Lane C — Body Section Mobile Polish

### Goal

Improve reading and scanning on mobile while preserving section content and
visual demos.

### Adopted Primitive

Use native disclosure:

```html
<details class="sg-readmore">
  <summary>Read more</summary>
  <p>...</p>
</details>
```

No new component cycle. No JS.

### Candidate Targets

Phase 1 must inventory which text blocks should become disclosure. Initial
targets:

- long section-intro paragraphs in `sg-section__head`,
- long `sg-helper` paragraphs with implementation caveats,
- Date/Time explanatory paragraphs,
- Text field caveat paragraphs,
- Carousel explanatory helper,
- any mobile section where explanation appears before the visual specimen and
  pushes the demo below the fold.

### Color Palette Polish

Current swatch grid:

```css
.sg-swatch-grid {
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
}
.sg-swatch__chip {
  aspect-ratio: 1 / 1;
}
```

Phase 1 must propose mobile values. Candidate:

```css
@media (max-width: 640px) {
  .sg-swatch-grid {
    grid-template-columns: repeat(auto-fill, minmax(104px, 1fr));
    gap: var(--space-sm);
  }
  .sg-swatch__chip {
    aspect-ratio: 1 / 0.72;
  }
}
```

Pass criterion: color surfaces scan visually at 390px without huge vertical
scroll tax.

## §8. Lane D — Acceptance Gate

Phase 3 must test against the user request log, not only implementation plan
tasks.

Required Phase 3 report:

```txt
Acceptance criterion | Evidence | PASS/FAIL
```

Phase 5 close may not proceed if:

- top app bar is absent at 390px,
- drawer cannot open/close,
- theme switcher is still text-only,
- body sections remain explanation-first on mobile,
- nav/body order drifts.

## §9. Files Expected In Scope

Likely edit targets:

- `products/reference-implementations/axismundi-lab/style-guide.html`
- `products/reference-implementations/axismundi-lab/scripts/style-guide.js`
- possibly `products/reference-implementations/axismundi-lab/scripts/theme.js`
- `tools/generators/publish_styleguide.py` only if mirror rewriting needs update
- generated `styleguide/index.html`
- generated `styleguide/scripts/*.js` if publisher copies changed scripts
- v3.5.17 phase docs

Expected non-edit targets:

- `components.css`
- `tokens.css`
- `blocks.css`
- `theme.json`
- lab module CSS/HTML pattern files
- module audit docs

## §10. Phase 1 Entry Conditions

Phase 1 must provide:

1. exact shell markup plan,
2. exact CSS breakpoint plan,
3. exact runtime wiring plan,
4. icon theme switcher glyph + ARIA contract,
5. body section disclosure target inventory,
6. color swatch responsive values,
7. Playwright acceptance script outline,
8. fallback plan if `<dialog>` behavior on file/GitHub Pages has an issue.

## §11. Phase 2 Preview

Expected Phase 2 deliverables:

- mobile top app bar,
- drawer dialog with nav + icon theme switcher,
- desktop aside preservation,
- icon theme switcher in desktop aside,
- body mobile polish CSS,
- selected disclosure conversions,
- regenerated `/styleguide/`,
- acceptance evidence.

## §12. Phase 5 Preview

If successful, Phase 5 should:

- update CHANGELOG v3.5.17,
- update ROADMAP v3.5.17 DONE + v3.6.0 or v3.5.18 NEXT,
- update CURRENT-STATE / NEXT-SESSION,
- record that v3.5.16 modernization was framing/plumbing and v3.5.17 delivered
  the actual shell rebuild,
- commit and push to GitHub Pages.

## §13. Phase 5 Close

Status: **CLOSED at v3.5.17 Phase 5** (2026-05-18).

The Phase 0 acceptance framing remained binding through implementation:
mobile top bar, Sheet-style `.sg-drawer`, icon theme switcher, body mobile
polish, typography-axis adjunct linking, and no Wave 2 component completion
claim. Phase 3 user QA passed after the typography-axis controls were made
collapsible.
